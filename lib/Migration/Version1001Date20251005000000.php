<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2025 Nikolaus Demmel <nikolaus@nikolaus-demmel.de>
 *
 * @author Nikolaus Demmel <nikolaus@nikolaus-demmel.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserVO\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add user metadata columns for VO synchronization:
 * - vo_user_id: VereinOnline user ID from API
 * - vo_group_ids: Cached group memberships (JSON)
 * - last_synced: Last sync timestamp
 */
class Version1001Date20251005000000 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('user_vo')) {
			$table = $schema->getTable('user_vo');

			// Add vo_user_id column if it doesn't exist
			if (!$table->hasColumn('vo_user_id')) {
				$table->addColumn('vo_user_id', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$output->info('Added column vo_user_id to user_vo table');
			}

			// Add vo_group_ids column if it doesn't exist
			if (!$table->hasColumn('vo_group_ids')) {
				$table->addColumn('vo_group_ids', Types::TEXT, [
					'notnull' => false,
				]);
				$output->info('Added column vo_group_ids to user_vo table');
			}

			// Add last_synced column if it doesn't exist
			if (!$table->hasColumn('last_synced')) {
				$table->addColumn('last_synced', Types::DATETIME, [
					'notnull' => false,
				]);
				$output->info('Added column last_synced to user_vo table');
			}

			// Add index on vo_user_id if it doesn't exist
			if (!$table->hasIndex('user_vo_vo_user_id')) {
				$table->addIndex(['vo_user_id'], 'user_vo_vo_user_id');
				$output->info('Added index on vo_user_id');
			}

			return $schema;
		}

		return null;
	}
}
