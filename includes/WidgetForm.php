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


namespace Modules\MaintenanceWidget\Includes;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldIntegerBox,
	CWidgetFieldSelect,
	CWidgetFieldTimePeriod
};

use CProfile;
use CWidgetsData;

/**
 * Maintenance Overview widget form.
 */
class WidgetForm extends CWidgetForm {
	public const MODE_ALL = 0;
	public const MODE_ACTIVE = 1;
	public const MODE_APPROACH = 2;
	public const MODE_EXPIRED = 3;

	public static function normalizeMode($value): int {
		if (is_int($value)) {
			return in_array($value, [self::MODE_ALL, self::MODE_ACTIVE, self::MODE_APPROACH, self::MODE_EXPIRED], true)
				? $value
				: self::MODE_ACTIVE;
		}

		if (is_string($value)) {
			return match ($value) {
				'all' => self::MODE_ALL,
				'active' => self::MODE_ACTIVE,
				'approach' => self::MODE_APPROACH,
				'expired' => self::MODE_EXPIRED,
				default => self::MODE_ACTIVE
			};
		}

		return self::MODE_ACTIVE;
	}

	public function addFields(): self {
		$default_mode = self::normalizeMode(CProfile::get('mnz.maintenance.widget.mode', self::MODE_ACTIVE));
		$default_limit = (int) CProfile::get('mnz.maintenance.widget.limit', 10);
		$default_show_scope = (int) CProfile::get('mnz.maintenance.widget.show_scope', 1);
		$default_show_description = (int) CProfile::get('mnz.maintenance.widget.show_description', 1);

		return $this
			->addField(
				(new CWidgetFieldSelect('mode', _('State'), [
					self::MODE_ALL => _('Any'),
					self::MODE_ACTIVE => _x('Active', 'maintenance status'),
					self::MODE_APPROACH => _x('Approaching', 'maintenance status'),
					self::MODE_EXPIRED => _x('Expired', 'maintenance status')
				]))->setDefault($default_mode)
			)
			->addField(
				(new CWidgetFieldIntegerBox('limit', _('Limit'), 1, 100))
					->setDefault($default_limit)
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldCheckBox('show_scope', _('Show scope (hosts and groups)')))
					->setDefault($default_show_scope)
			)
			->addField(
				(new CWidgetFieldCheckBox('show_description', _('Show description')))
					->setDefault($default_show_description)
			)
			->addField(
				(new CWidgetFieldTimePeriod('time_period', _('Time period')))
					->setDefault([
						CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
							CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
						)
					])
					->setDefaultPeriod(['from' => 'now-30d', 'to' => 'now'])
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			);
	}
}

