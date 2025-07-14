<?php
script('user_vo', 'admin');
style('user_vo', 'admin');
?>

<div id="user_vo_admin" class="section">
    <h2><?php p($l->t('VereinOnline User Authentication')); ?></h2>
    <div class="duplicate-accounts-section">
        <h3><?php p($l->t('Duplicate Account Management')); ?></h3>
        <p><?php p($l->t('This tool helps you identify and manage duplicate user accounts that were created due to a case sensitivity bug in version 0.1.2 and earlier of the user_vo plugin (see ')); ?><a href="https://github.com/NikolausDemmel/user_vo/issues/2" target="_blank" rel="noopener">GitHub issue #2</a><?php p($l->t('). When users logged in with different capitalizations of their username, multiple accounts were created for the same person.')); ?></p>
        <p><?php p($l->t('Use this interface to scan for duplicates and decide which accounts to keep visible. After exposing duplicate accounts, users can log into them to retrieve files or data, and you can then delete unwanted accounts through the user management interface or using the occ user:delete command.')); ?></p>
        
        <div class="admin-controls">
            <button id="scan-duplicates" class="btn btn-primary">
                <?php p($l->t('Scan for Duplicates')); ?>
            </button>
        </div>
        
        <div id="duplicate-results" style="display: none;">
            <h4><?php p($l->t('Duplicate users found')); ?></h4>
            <div id="duplicate-list"></div>
        </div>
    </div>
</div> 
