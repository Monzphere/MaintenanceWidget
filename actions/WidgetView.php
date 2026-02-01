<?php declare(strict_types = 0);
/*
** Copyright (C) 2026 Monzphere
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


namespace Modules\MaintenanceWidget\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CProfile,
	CRoleHelper,
	CUrl,
	CWebUser;

use Modules\MaintenanceWidget\Includes\WidgetForm;

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'initial_load' => 'in 0,1'
		]);
	}

	protected function doAction(): void {
		$default_name = $this->widget->getDefaultName();
		$name = trim((string) $this->getInput('name', $default_name));
		if ($name === '') {
			$name = $default_name;
		}
		$name = self::sanitizeWidgetName($name, $default_name, []);
		$now = time();

		$mode = WidgetForm::normalizeMode($this->fields_values['mode']
			?? CProfile::get('mnz.maintenance.widget.mode', WidgetForm::MODE_ACTIVE));
		$limit = (int) ($this->fields_values['limit']
			?? CProfile::get('mnz.maintenance.widget.limit', 10));
		$show_scope = (int) ($this->fields_values['show_scope']
			?? CProfile::get('mnz.maintenance.widget.show_scope', 1)) === 1;
		$show_description = (int) ($this->fields_values['show_description']
			?? CProfile::get('mnz.maintenance.widget.show_description', 1)) === 1;

		$time_period = $this->fields_values['time_period'] ?? null;
		$time_from = (int) ($time_period['from_ts'] ?? 0);
		$time_to = (int) ($time_period['to_ts'] ?? 0);
		if ($time_from <= 0 || $time_to <= 0) {
			$time_to = $now;
			$time_from = $now - 30 * 86400;
		}
		if ($time_to < $time_from) {
			[$time_from, $time_to] = [$time_to, $time_from];
		}

		$limit = max(1, min($limit, 100));

		CProfile::update('mnz.maintenance.widget.mode', $mode, PROFILE_TYPE_INT);
		CProfile::update('mnz.maintenance.widget.limit', $limit, PROFILE_TYPE_INT);
		CProfile::update('mnz.maintenance.widget.show_scope', $show_scope ? 1 : 0, PROFILE_TYPE_INT);
		CProfile::update('mnz.maintenance.widget.show_description', $show_description ? 1 : 0, PROFILE_TYPE_INT);
		CProfile::update('mnz.maintenance.widget.last_view', $now, PROFILE_TYPE_INT);

		$user_type = CWebUser::getType();
		$can_view = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_MAINTENANCE);

		if (!$can_view) {
			$this->setResponse(new CControllerResponseData([
				'name' => $name,
				'error' => _('You do not have permission to view maintenance data.'),
				'maintenances' => [],
				'summary' => [
					'total' => 0,
					'active' => 0,
					'approaching' => 0,
					'expired' => 0,
					'eligible' => 0,
					'shown' => 0,
					'limit' => $limit
				],
				'options' => [
					'mode' => $mode,
					'limit' => $limit,
					'show_scope' => $show_scope,
					'show_description' => $show_description
				],
				'period' => [
					'from' => $time_from,
					'to' => $time_to
				],
				'generated_at' => $now,
				'links' => [
					'list' => (new CUrl('zabbix.php'))->setArgument('action', 'maintenance.list')->getUrl()
				],
				'user' => [
					'debug_mode' => $this->getDebugMode(),
					'user_type' => $user_type
				]
			]));
			return;
		}

		$error = null;
		$raw = [];
		try {
			$options = [
				'output' => [
					'maintenanceid', 'name', 'description',
					'maintenance_type', 'active_since', 'active_till'
				],
				'sortfield' => 'active_since',
				'sortorder' => ZBX_SORT_UP,
				'preservekeys' => true
			];

			if ($show_scope) {
				$options['selectHosts'] = ['hostid', 'name'];
				$options['selectHostGroups'] = ['groupid', 'name'];
			}

			$raw = API::Maintenance()->get($options);
		}
		catch (\Throwable $e) {
			$error = _('Cannot retrieve maintenance data.');
		}

		$maintenances = [];
		$summary = [
			'total' => 0,
			'active' => 0,
			'approaching' => 0,
			'expired' => 0,
			'eligible' => 0,
			'shown' => 0,
			'limit' => $limit
		];

		foreach ($raw as $maintenance) {
			if (!self::overlapsPeriod($time_from, $time_to, $maintenance)) {
				continue;
			}

			$status = self::resolveStatus($now, $maintenance);

			$summary['total']++;
			if ($status === MAINTENANCE_STATUS_ACTIVE) {
				$summary['active']++;
			}
			elseif ($status === MAINTENANCE_STATUS_APPROACH) {
				$summary['approaching']++;
			}
			elseif ($status === MAINTENANCE_STATUS_EXPIRED) {
				$summary['expired']++;
			}

			if (!self::modeMatches($mode, $status)) {
				continue;
			}

			$summary['eligible']++;

			$host_names = [];
			$group_names = [];

			if ($show_scope) {
				if (array_key_exists('hosts', $maintenance) && is_array($maintenance['hosts'])) {
					foreach ($maintenance['hosts'] as $host) {
						if (!is_array($host)) {
							continue;
						}
						$name = (string) ($host['name'] ?? $host['host'] ?? '');
						if ($name !== '') {
							$host_names[] = $name;
						}
					}
				}

				$groups_source = $maintenance['hostgroups'] ?? ($maintenance['groups'] ?? []);
				if (is_array($groups_source)) {
					foreach ($groups_source as $group) {
						if (!is_array($group)) {
							continue;
						}
						$name = (string) ($group['name'] ?? '');
						if ($name !== '') {
							$group_names[] = $name;
						}
					}
				}

				if ($host_names) {
					$host_names = array_values(array_unique($host_names));
					sort($host_names, SORT_NATURAL | SORT_FLAG_CASE);
				}
				if ($group_names) {
					$group_names = array_values(array_unique($group_names));
					sort($group_names, SORT_NATURAL | SORT_FLAG_CASE);
				}
			}

			$maintenances[] = [
				'maintenanceid' => (int) $maintenance['maintenanceid'],
				'name' => (string) $maintenance['name'],
				'description' => (string) ($maintenance['description'] ?? ''),
				'maintenance_type' => (int) ($maintenance['maintenance_type'] ?? 0),
				'active_since' => (int) ($maintenance['active_since'] ?? 0),
				'active_till' => (int) ($maintenance['active_till'] ?? 0),
				'status' => $status,
				'scope' => [
					'hosts' => count($host_names),
					'groups' => count($group_names),
					'hosts_names' => $host_names,
					'group_names' => $group_names
				]
			];
		}

		if ($limit > 0 && count($maintenances) > $limit) {
			$maintenances = array_slice($maintenances, 0, $limit);
		}
		$summary['shown'] = count($maintenances);

		$name = self::sanitizeWidgetName($name, $default_name, $maintenances);

		$this->setResponse(new CControllerResponseData([
			'name' => $name,
			'error' => $error,
			'maintenances' => $maintenances,
			'summary' => $summary,
			'options' => [
				'mode' => $mode,
				'limit' => $limit,
				'show_scope' => $show_scope,
				'show_description' => $show_description
			],
			'period' => [
				'from' => $time_from,
				'to' => $time_to
			],
			'generated_at' => $now,
			'links' => [
				'list' => (new CUrl('zabbix.php'))->setArgument('action', 'maintenance.list')->getUrl()
			],
			'user' => [
				'debug_mode' => $this->getDebugMode(),
				'user_type' => $user_type
			]
		]));
	}

	private static function resolveStatus(int $now, array $maintenance): int {
		$since = (int) ($maintenance['active_since'] ?? 0);
		$till = (int) ($maintenance['active_till'] ?? 0);

		if ($since > $now) {
			return MAINTENANCE_STATUS_APPROACH;
		}

		if ($till > 0 && $till < $now) {
			return MAINTENANCE_STATUS_EXPIRED;
		}

		return MAINTENANCE_STATUS_ACTIVE;
	}

	private static function overlapsPeriod(int $from, int $to, array $maintenance): bool {
		$since = (int) ($maintenance['active_since'] ?? 0);
		$till = (int) ($maintenance['active_till'] ?? 0);

		if ($till === 0) {
			$till = $since;
		}

		return !($till < $from || $since > $to);
	}

	private static function sanitizeWidgetName(string $name, string $default, array $maintenances): string {
		if ($name === '') {
			return $default;
		}

		if (stripos($name, '{HOST') !== false) {
			return $default;
		}

		if (!$maintenances) {
			return $name;
		}

		$lower_name = self::normalizeString($name);

		foreach ($maintenances as $maintenance) {
			$hosts = $maintenance['scope']['hosts_names'] ?? [];
			$groups = $maintenance['scope']['group_names'] ?? [];

			foreach ($hosts as $host_name) {
				if (self::normalizeString($host_name) === $lower_name) {
					return $default;
				}
			}

			foreach ($groups as $group_name) {
				if (self::normalizeString($group_name) === $lower_name) {
					return $default;
				}
			}
		}

		return $name;
	}

	private static function normalizeString(string $value): string {
		return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
	}

	private static function modeMatches(int $mode, int $status): bool {
		return match ($mode) {
			WidgetForm::MODE_ACTIVE => $status === MAINTENANCE_STATUS_ACTIVE,
			WidgetForm::MODE_APPROACH => $status === MAINTENANCE_STATUS_APPROACH,
			WidgetForm::MODE_EXPIRED => $status === MAINTENANCE_STATUS_EXPIRED,
			default => true
		};
	}
}

