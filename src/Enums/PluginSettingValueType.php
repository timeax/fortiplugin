<?php

namespace Timeax\FortiPlugin\Enums;

enum PluginSettingValueType: string
{
	case text = "text";
	case toggle = "toggle";
	case tristate = "tristate";
	case password = "password";
	case email = "email";
	case number = "number";
	case tel = "tel";
	case url = "url";
	case search = "search";
	case chips = "chips";
	case checkbox = "checkbox";
	case radio = "radio";
	case color = "color";
	case range = "range";
	case select = "select";
	case multiselect = "multiselect";
	case date = "date";
	case time = "time";
	case datetime_local = "datetime_local";
	case month = "month";
	case week = "week";
	case file = "file";
	case json = "json";
}
