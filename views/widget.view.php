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


/**
 * Maintenance Overview widget view.
 *
 * @var CView $this
 * @var array $data
 */

$table = new CTableInfo();

if ($data['error'] !== null) {
	$table->setNoDataMessage($data['error']);
}
else {
	$show_scope = (bool) ($data['options']['show_scope'] ?? false);
	$show_description = (bool) ($data['options']['show_description'] ?? false);
	$inline_scope_limit = 3;
	$hint_scope_limit = 30;

	$build_scope_line = static function(string $label, array $names) use ($inline_scope_limit, $hint_scope_limit) {
		$names = array_values(array_filter(array_map('trim', $names), static fn($value) => $value !== ''));
		if (!$names) {
			return null;
		}

		$inline = array_slice($names, 0, $inline_scope_limit);
		$extra = count($names) - count($inline);

		$line_items = [
			(new CSpan($label.': '))->addClass('mnz-maintenance-scope-label'),
			(new CSpan(implode(', ', $inline)))->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
		];

		if ($extra > 0) {
			$hint_items = [];
			$hint_names = array_slice($names, 0, $hint_scope_limit);
			foreach ($hint_names as $index => $name) {
				$hint_items[] = new CSpan($name);
				if ($index < count($hint_names) - 1) {
					$hint_items[] = BR();
				}
			}
			if (count($names) > $hint_scope_limit) {
				$hint_items[] = BR();
				$hint_items[] = new CSpan(_s('... and %1$s more', count($names) - $hint_scope_limit));
			}

			$line_items[] = (new CButtonIcon(ZBX_ICON_MORE))
				->setHint($hint_items, ZBX_STYLE_HINTBOX_WRAP);
		}

		return (new CDiv($line_items))->addClass('mnz-maintenance-scope');
	};

	$header = [
		_('Maintenance'),
		_('Status')
	];

	$table->setHeader($header);

	foreach ($data['maintenances'] as $maintenance) {
		$status_label = _x('Unknown', 'maintenance status');
		$status_class = ZBX_STYLE_GREY;

		switch ((int) $maintenance['status']) {
			case MAINTENANCE_STATUS_ACTIVE:
				$status_label = _x('Active', 'maintenance status');
				$status_class = ZBX_STYLE_GREEN;
				break;

			case MAINTENANCE_STATUS_APPROACH:
				$status_label = _x('Approaching', 'maintenance status');
				$status_class = ZBX_STYLE_ORANGE;
				break;

			case MAINTENANCE_STATUS_EXPIRED:
				$status_label = _x('Expired', 'maintenance status');
				$status_class = ZBX_STYLE_RED;
				break;
		}

		$status = (new CSpan($status_label))->addClass($status_class);

		$window = _s('%1$s - %2$s',
			zbx_date2str(DATE_TIME_FORMAT, (int) $maintenance['active_since']),
			zbx_date2str(DATE_TIME_FORMAT, (int) $maintenance['active_till'])
		);

		$type = ((int) $maintenance['maintenance_type'] === 1)
			? _('No data collection')
			: _('With data collection');

		$filter_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'maintenance.list')
			->setArgument('filter_set', 1)
			->setArgument('filter_name', (string) $maintenance['name'])
			->setArgument('filter_status', (int) $maintenance['status'])
			->getUrl();

		$meta_parts = [
			_s('Window: %1$s', $window),
			_s('Type: %1$s', $type)
		];

		$description = (string) ($maintenance['description'] ?? '');

		$title_items = [new CLink($maintenance['name'], $filter_url)];
		if ($show_description && $description !== '') {
			$title_items[] = new CSpan(' ');
			$title_items[] = makeDescriptionIcon($description)->addClass('mnz-maintenance-desc-icon');
		}

		$main_items = [
			(new CDiv($title_items))->addClass('mnz-maintenance-title'),
			(new CDiv(implode(' | ', $meta_parts)))
				->addClass('mnz-maintenance-meta')
				->addClass(ZBX_STYLE_GREY)
		];

		if ($show_scope) {
			$hosts_names = $maintenance['scope']['hosts_names'] ?? [];
			$groups_names = $maintenance['scope']['group_names'] ?? [];

			$hosts_line = $build_scope_line(_s('Hosts (%1$s)', count($hosts_names)), $hosts_names);
			$groups_line = $build_scope_line(_s('Host groups (%1$s)', count($groups_names)), $groups_names);

			if ($hosts_line !== null) {
				$hosts_line->addClass(ZBX_STYLE_GREY);
				$main_items[] = $hosts_line;
			}
			if ($groups_line !== null) {
				$groups_line->addClass(ZBX_STYLE_GREY);
				$main_items[] = $groups_line;
			}
		}

		$main = (new CDiv($main_items))->addClass('mnz-maintenance-main');

		$row = [
			(new CCol($main))->addClass(ZBX_STYLE_WORDBREAK),
			(new CCol($status))->addClass(ZBX_STYLE_NOWRAP)
		];

		$table->addRow($row);
	}

	if (!$data['maintenances']) {
		$table->setNoDataMessage(_('No maintenance periods found.'));
	}
	elseif (($data['summary']['total'] ?? 0) > 0) {
		$list_reset_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'maintenance.list')
			->setArgument('filter_rst', 1)
			->getUrl();

		$summary_parts = [
			new CSpan(_s('Total: %1$s', $data['summary']['total'] ?? 0)),
			new CSpan(' | '),
			(new CSpan(_s('Active: %1$s', $data['summary']['active'] ?? 0)))->addClass(ZBX_STYLE_GREEN),
			new CSpan(' | '),
			(new CSpan(_s('Approaching: %1$s', $data['summary']['approaching'] ?? 0)))->addClass(ZBX_STYLE_ORANGE),
			new CSpan(' | '),
			(new CSpan(_s('Expired: %1$s', $data['summary']['expired'] ?? 0)))->addClass(ZBX_STYLE_RED),
			new CSpan(' | '),
			new CSpan(_s('Showing: %1$s of %2$s (limit %3$s)',
				$data['summary']['shown'] ?? 0,
				$data['summary']['eligible'] ?? 0,
				$data['summary']['limit'] ?? 0
			)),
			new CSpan(' | '),
			new CLink(_('Open maintenance list'), $list_reset_url)
		];

		if (!empty($data['period']['from']) && !empty($data['period']['to'])) {
			$summary_parts[] = new CSpan(' | ');
			$summary_parts[] = new CSpan(_s('Period: %1$s - %2$s',
				zbx_date2str(DATE_TIME_FORMAT, (int) $data['period']['from']),
				zbx_date2str(DATE_TIME_FORMAT, (int) $data['period']['to'])
			));
		}

		$table->setFooter([
			(new CCol($summary_parts))
				->setColSpan(count($header))
				->addClass(ZBX_STYLE_GREY)
		]);
	}
}

$item = (new CDiv($table))->addClass('mnz-maintenance-widget');

(new CWidgetView($data))
	->addItem($item)
	->show();

