<?php

namespace OCA\UserVO\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IDBConnection;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

class AdminController extends Controller {

    private $connection;
    private $logger;
    private $groupManager;

    public function __construct($appName, IRequest $request, IDBConnection $connection, LoggerInterface $logger, IGroupManager $groupManager) {
        parent::__construct($appName, $request);
        $this->connection = $connection;
        $this->logger = $logger;
        $this->groupManager = $groupManager;
    }

    /**
     * Admin settings page
     */
    public function index() {
        return new TemplateResponse('user_vo', 'admin', [], 'admin');
    }



    /**
     * Helper to strip the !duplicate marker from a uid
     */
    private function stripDuplicateMarker($uid) {
        if (str_ends_with($uid, '!duplicate')) {
            return substr($uid, 0, -10);  // Correct: removes all 10 characters
        }
        return $uid;
    }

    /**
     * Scan for duplicates and return comprehensive user analysis
     */
    public function scanDuplicates() {
        try {
            // Get all users from oc_accounts
            $accountsQuery = $this->connection->getQueryBuilder();
            $accountsQuery->select('uid')
                ->from('accounts')
                ->orderBy('uid');
            $accountsResult = $accountsQuery->execute();
            $allAccountUsers = $accountsResult->fetchAll();
            $accountsResult->closeCursor();

            // Get all user_vo entries (to determine exposure status)
            $userVoQuery = $this->connection->getQueryBuilder();
            $userVoQuery->select('uid', 'displayname')
                ->from('user_vo')
                ->where($userVoQuery->expr()->eq('backend', $userVoQuery->createNamedParameter('user_vo')));
            $userVoResult = $userVoQuery->execute();
            $userVoEntries = $userVoResult->fetchAll();
            $userVoResult->closeCursor();

            // Create map of exposed users (exist in user_vo)
            $exposedUsers = [];
            foreach ($userVoEntries as $row) {
                $exposedUsers[$row['uid']] = $row['displayname'];
            }

            // Filter accounts to only include user_vo-managed users (case-insensitive match)
            $managedUsers = [];
            foreach ($allAccountUsers as $accountUser) {
                $normalizedAccount = strtolower($accountUser['uid']);
                // Check if this account has a corresponding entry in user_vo (canonical or marked)
                foreach ($exposedUsers as $voUid => $voDisplayname) {
                    if (strtolower($this->stripDuplicateMarker($voUid)) === $normalizedAccount) {
                        $managedUsers[] = $accountUser['uid'];
                        break;
                    }
                }
            }

            // Group managed users by normalized username
            $userGroups = [];
            foreach ($managedUsers as $uid) {
                $normalizedUid = strtolower($uid);
                if (!isset($userGroups[$normalizedUid])) {
                    $userGroups[$normalizedUid] = [];
                }
                $userGroups[$normalizedUid][] = $uid;
            }

            // Prepare response arrays
            $duplicateGroups = [];
            $allPluginUsers = [];

            // Process each user group
            foreach ($userGroups as $normalizedUid => $variants) {
                // Find canonical user (exists in user_vo without !duplicate marker)
                $canonical = $this->findCanonicalUser($normalizedUid);

                $variantData = [];
                foreach ($variants as $uid) {
                    $isCanonical = ($uid === $canonical);
                    $markedUid = $uid . '!duplicate';
                    $isExposed = array_key_exists($uid, $exposedUsers) || array_key_exists($markedUid, $exposedUsers);
                    $isDuplicate = array_key_exists($markedUid, $exposedUsers);
                    
                    // Get display name from user_vo if exposed, otherwise use uid
                    $displayname = '';
                    if (array_key_exists($uid, $exposedUsers)) {
                        $displayname = !empty($exposedUsers[$uid]) ? $exposedUsers[$uid] : $uid;
                    } elseif (array_key_exists($markedUid, $exposedUsers)) {
                        $displayname = !empty($exposedUsers[$markedUid]) ? $exposedUsers[$markedUid] : $uid;
                    } else {
                        $displayname = $uid;
                    }
                    
                    $variantData[] = [
                        'uid' => $uid,  // Clean uid for frontend
                        'display_uid' => $uid,
                        'is_exposed' => $isExposed,
                        'is_canonical' => $isCanonical,
                        'is_marked_duplicate' => $isDuplicate,
                        'file_count' => $this->countUserFiles($uid),
                        'displayname' => $displayname,
                        'groups' => $this->getUserGroups($uid),
                        'creation_date' => $this->getUserDirectoryCreationDate($uid),
                        'is_normalized' => ($uid === $normalizedUid),
                    ];
                }

                $groupInfo = [
                    'normalized_uid' => $normalizedUid,
                    'variants' => $variantData,
                ];

                // Add to appropriate categories
                if (count($variants) > 1) {
                    // Multiple variants = duplicate group
                    $duplicateGroups[] = $groupInfo;
                }

                // Add all variants to the comprehensive list
                foreach ($variantData as $variant) {
                    $allPluginUsers[] = $variant;
                }
            }

            // Sort arrays for consistent display
            usort($duplicateGroups, function($a, $b) {
                return strcmp($a['normalized_uid'], $b['normalized_uid']);
            });
            usort($allPluginUsers, function($a, $b) {
                return strcmp($a['uid'], $b['uid']);
            });

            return new JSONResponse([
                'success' => true, 
                'duplicateSets' => $duplicateGroups,
                'allPluginUsers' => $allPluginUsers,
                'summary' => [
                    'duplicateSets' => count($duplicateGroups),
                    'totalManagedUsers' => count($allPluginUsers)
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in scanDuplicates: ' . $e->getMessage(), ['app' => 'user_vo']);
            return new JSONResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Expose a user: add to user_vo with !duplicate marker
     */
    public function exposeUser() {
        $data = $this->request->getParams();
        $uid = $data['uid'] ?? null;
        if (!$uid) {
            return new JSONResponse(['success' => false, 'error' => 'No uid provided']);
        }
        
        // Add !duplicate marker to the uid
        $markedUid = $uid . '!duplicate';
        
        // Only add if not already present
        $query = $this->connection->getQueryBuilder();
        $query->select('uid')
            ->from('user_vo')
            ->where($query->expr()->eq('uid', $query->createNamedParameter($markedUid)))
            ->andWhere($query->expr()->eq('backend', $query->createNamedParameter('user_vo')));
        $result = $query->execute();
        $row = $result->fetch();
        $result->closeCursor();
        if ($row) {
            return new JSONResponse(['success' => true, 'message' => 'Already exposed']);
        }
        
        $insert = $this->connection->getQueryBuilder();
        $insert->insert('user_vo')
            ->values([
                'uid' => $insert->createNamedParameter($markedUid),
                'backend' => $insert->createNamedParameter('user_vo'),
                'displayname' => $insert->createNamedParameter($uid),
            ]);
        $insert->execute();
        return new JSONResponse(['success' => true]);
    }

    /**
     * Hide a user: remove from user_vo (unless canonical)
     */
    public function hideUser() {
        $data = $this->request->getParams();
        $uid = $data['uid'] ?? null;
        if (!$uid) {
            return new JSONResponse(['success' => false, 'error' => 'No uid provided']);
        }
        
        $normalizedUid = strtolower($uid);
        $canonical = $this->findCanonicalUser($normalizedUid);
        
        // Don't allow hiding canonical users
        if ($uid === $canonical) {
            return new JSONResponse(['success' => false, 'error' => 'Cannot hide canonical user']);
        }
        
        // Remove the marked duplicate entry (uid + !duplicate)
        $markedUid = $uid . '!duplicate';
        $delete = $this->connection->getQueryBuilder();
        $delete->delete('user_vo')
            ->where($delete->expr()->eq('uid', $delete->createNamedParameter($markedUid)))
            ->andWhere($delete->expr()->eq('backend', $delete->createNamedParameter('user_vo')));
        $delete->execute();
        
        return new JSONResponse(['success' => true]);
    }



    /**
     * Find the canonical user (first one without !duplicate marker) for a normalized username
     */
    private function findCanonicalUser($normalizedUid) {
        $query = $this->connection->getQueryBuilder();
        $query->select('uid')
            ->from('user_vo')
            ->where($query->expr()->eq('backend', $query->createNamedParameter('user_vo')))
            ->andWhere($query->expr()->notLike('uid', $query->createNamedParameter('%!duplicate')))
            ->andWhere($query->expr()->eq(
                $query->func()->lower('uid'), 
                $query->createNamedParameter($normalizedUid)
            ))
            ->setMaxResults(1);
        $result = $query->execute();
        $row = $result->fetch();
        $result->closeCursor();
        return $row ? $row['uid'] : null;
    }

    /**
     * Count files for a user (in their data directory)
     */
    private function countUserFiles($uid) {
        $dataDir = \OC::$server->getConfig()->getSystemValue('datadirectory', '/var/www/html/data');
        $userDir = $dataDir . '/' . $uid . '/files';
        if (!is_dir($userDir)) {
            return 0;
        }
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($userDir, \RecursiveDirectoryIterator::SKIP_DOTS));
        $count = 0;
        foreach ($rii as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get groups for a user
     */
    private function getUserGroups($uid) {
        $user = \OC::$server->getUserManager()->get($uid);
        if (!$user) {
            return [];
        }
        
        $groups = $this->groupManager->getUserGroups($user);
        $groupNames = [];
        foreach ($groups as $group) {
            $groupNames[] = $group->getGID();
        }
        return $groupNames;
    }

    /**
     * Get user directory creation date (using birth time if available, fallback to oldest file)
     */
    private function getUserDirectoryCreationDate($uid) {
        $dataDir = \OC::$server->getConfig()->getSystemValue('datadirectory', '/var/www/html/data');
        $userDir = $dataDir . '/' . $uid;
        
        if (!is_dir($userDir)) {
            return null;
        }
        
        // Try to get birth time using stat command (Linux systems)
        $birthTime = $this->getBirthTime($userDir);
        if ($birthTime !== null) {
            return date('Y-m-d H:i:s', $birthTime);
        }
        
        // Fallback: find the oldest file in the user directory
        $oldestTime = $this->findOldestFileTime($userDir);
        if ($oldestTime !== null) {
            return date('Y-m-d H:i:s', $oldestTime);
        }
        
        return null;
    }
    
    /**
     * Get birth time (creation time) using stat command
     */
    private function getBirthTime($path) {
        $escapedPath = escapeshellarg($path);
        $output = shell_exec("stat -c %W '$escapedPath' 2>/dev/null");
        
        if ($output !== null) {
            $birthTime = (int)trim($output);
            // %W returns 0 if birth time is not available
            if ($birthTime > 0) {
                return $birthTime;
            }
        }
        
        // Try alternative method for systems that support it
        $output = shell_exec("stat -f %B '$escapedPath' 2>/dev/null");
        if ($output !== null) {
            $birthTime = (int)trim($output);
            if ($birthTime > 0) {
                return $birthTime;
            }
        }
        
        return null;
    }
    
    /**
     * Find the oldest file in the user directory as fallback
     */
    private function findOldestFileTime($userDir) {
        $oldestTime = null;
        
        // Check common files that are created early
        $checkFiles = [
            $userDir . '/files',
            $userDir . '/cache',
            $userDir . '/files_trashbin'
        ];
        
        foreach ($checkFiles as $file) {
            if (file_exists($file)) {
                $time = filectime($file);
                if ($time !== false && ($oldestTime === null || $time < $oldestTime)) {
                    $oldestTime = $time;
                }
            }
        }
        
        return $oldestTime;
    }
} 
