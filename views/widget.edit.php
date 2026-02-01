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
 * Maintenance Overview widget form view.
 *
 * @var CView $this
 * @var array $data
 */

$form = (new CWidgetFormView($data));

$form
	->addField(array_key_exists('mode', $data['fields']) && $data['fields']['mode'] !== null
		? new CWidgetFieldSelectView($data['fields']['mode'])
		: null
	)
	->addField(array_key_exists('limit', $data['fields']) && $data['fields']['limit'] !== null
		? new CWidgetFieldIntegerBoxView($data['fields']['limit'])
		: null
	)
	->addField(array_key_exists('show_scope', $data['fields']) && $data['fields']['show_scope'] !== null
		? new CWidgetFieldCheckBoxView($data['fields']['show_scope'])
		: null
	)
	->addField(array_key_exists('show_description', $data['fields']) && $data['fields']['show_description'] !== null
		? new CWidgetFieldCheckBoxView($data['fields']['show_description'])
		: null
	)
	->addField(array_key_exists('time_period', $data['fields']) && $data['fields']['time_period'] !== null
		? (new CWidgetFieldTimePeriodView($data['fields']['time_period']))
			->setDateFormat(ZBX_FULL_DATE_TIME)
			->setFromPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
			->setToPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
		: null
	)
	->show();

