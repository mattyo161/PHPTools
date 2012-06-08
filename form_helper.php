<?php 

class FH_Form {
	private $fields;
	private $messages;
	private $valid;
	
	function __construct($vFields) {
		$this->fields = Array();
		$this->messages = Array();
		$this->valid = false;
		foreach ($vFields as $field) {
			$this->add($field);
		}
	}
	
	function add($vField) {
		array_push($this->fields, $vField);
	}
	
	function field_by_name($name) {
		foreach ($this->fields as $field) {
			if ($field->get_name() == $name) {
				return $field;
			}
		}
	}
	
	function field_by_id($id) {
		foreach ($this->fields as $field) {
			if ($field->get_id() == $id) {
				return $field;
			}
		}
	}
	
	function add_message($message, $field) {
		array_push($this->messages, new FH_Message($message, $field));
	}
	
	function get_messages() {
		return $this->messages;
	}
	
	function is_valid() {
		return $this->valid == true;
	}
	
	/**
	 * Load values for all the properties based on the provided hash
	 */
	function load_values($data = false) {
		if ($data == false) {
			$data = $_POST;
		}
		foreach ($this->fields as $field) {
			if (isset($data[$field->get_name()])) {
				$field->set_value($data[$field->get_name()]);
			}
		}
	}
	
	/**
	 * Validate all data against $_POST by default or a passed array
	 */
	function validate($data = false) {
		if ($data == false) {
			$data = $_POST;
		}
		$this->valid = true;
		foreach ($this->fields as $field) {
			# make sure the field is not missing
			if ($field->is_required() and isset($data[$field->get_name()]) and $data[$field->get_name()] != "") {
				$value = $data[$field->get_name()];
				# lets make sure the value is correct
				# some filters taken from
				# http://komunitasweb.com/2009/03/10-practical-php-regular-expression-recipes/
				if ($field->get_format() == FH_Formats::DATE) {
					if (preg_match("/^(\d{1,2})[\/\.](\d{1,2})[\/\.](\d{2,4})$/", $value, $matches)) {
						$value = $matches[1] . "/" . $matches[2] . "/" . $matches[3];
					} else {
						$field->add_status(FH_Statuses::ERROR);
						$this->add_message("ERROR field " . $field->get_label() . " needs to be in format m/d/yyyy", $field);
						$this->valid = false;
					}
				} else if ($field->get_format() == FH_Formats::PRICE) {
					$value = floatval(preg_replace("/[^\-\d\.]/","", $value));
					#$value = number_format($value, 2);
					if ($value <= 0) {
						$field->add_status(FH_Statuses::ERROR);
						$this->add_message("ERROR field " . $field->get_label() . " must be greater then 0", $field);
						$this->valid = false;
					}
				} else if ($field->get_format() == FH_Formats::CURRENCY) {
					$value = floatval(preg_replace("/[^\-\d\.]/","", $value));
					$value = money_format($value);
					add_debug_text($field->get_name() . " = " . $value);
					if ($value <= 0) {
						$field->add_status(FH_Statuses::ERROR);
						$this->add_message("ERROR field " . $field->get_label() . " must be greater then 0", $field);
						$this->valid = false;
					}
				} else if ($field->get_format() == FH_Formats::STATE) {
					if (preg_match("/^\w\w$/", $value, $matches)) {
						$value = strtoupper($value);
					} else {
						$field->add_status(FH_Statuses::ERROR);
						$this->add_message("ERROR field " . $field->get_label() . " 2 letters", $field);
						$this->valid = false;
					}
				} else if ($field->get_format() == FH_Formats::ZIP) {
					if (preg_match("/^(\d\d\d\d\d)/", $value, $matches)) {
						$value = $matches[1];
					} else {
						$field->add_status(FH_Statuses::ERROR);
						$this->add_message("ERROR field " . $field->get_label() . " 5 digit zip code", $field);
						$this->valid = false;
					}
				} else if ($field->get_format() == FH_Formats::EMAIL) {
					#if (eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $value) {
					if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
						$value = strtolower($value);
					} else {
						$value = strtolower(preg_replace("/[\s]/", "", $value));
						$field->add_status(FH_Statuses::ERROR);
						$this->add_message("ERROR field " . $field->get_label() . " must be a valid email", $field);
						$this->valid = false;
					}
				} else if ($field->get_format() == FH_Formats::PHONE) {
					if (preg_match('/\(?\d{3}\)?[-\s.]?\d{3}[-\s.]\d{4}/x', $value)) {
						$value = strtolower($value);
					} else {
						$value = strtolower(preg_replace("/[\s]/", "", $value));
						$field->add_status(FH_Statuses::ERROR);
						$this->add_message("ERROR field " . $field->get_label() . " must be a phone number in the form 999-999-9999", $field);
						$this->valid = false;
					}
				}
				if ($field->is_numeric()) {
					# we need to make sure that this is a number
					if (!is_numeric($field->get_value())) {
						$field->add_status(FH_Statuses::ERROR);
						$this->add_message("ERROR field " . $field->get_label() . " must be a number", $field);
					}
					if (!is_null($field->get_min()) and $field->get_min() != '') { 
						if ($field->get_value() < $field->get_min()) {
							$field->add_status(FH_Statuses::ERROR);
							$this->add_message("ERROR field " . $field->get_label() . " must be greater then or equal to " . $field->get_min(), $field);
						}
					}
					if (!is_null($field->get_max()) and $field->get_min() != '') { 
						if ($field->get_value() > $field->get_max() and $field->get_max() != '') {
							$field->add_status(FH_Statuses::ERROR);
							$this->add_message("ERROR field " . $field->get_label() . " must be less then or equal to " . $field->get_max(), $field);
						}
					}
				}
				$field->set_value($value);
			} else if ($field->is_required()) {
				# field is missing
				$field->add_status(FH_Statuses::MISSING);
				$this->add_message("Missing field " . $field->get_label(), $field);
				$this->valid = false;
			}
		}
	}
	
}

class FH_Message {
	private $message;
	private $field;
	
	function __construct($vMessage, $vField) {
		$this->message = $vMessage;
		$this->field = $vField;
	}
	
	function get_message() {
		return $this->message;
	}
	
	function get_field() {
		return $this->field;
	}
}

class FH_Formats {
	const TEXT = 1;
	const DATE = 2;
	const TIME = 3;
	const DATETIME = 4;
	const PRICE = 5;
	const BOOLEAN = 6;
	const CURRENCY = 7;
	const HIDDEN = 8;
	const STATE = 9;
	const ZIP = 10;
	const EMAIL = 11;
	const CC = 12;
	const CCCODE = 13;
	const PHONE = 14;
	const INTEGER = 15;
	const TEXTAREA = 16;
	const FLOAT = 17;
	const SELECT = 18;
}

class FH_Statuses {
	const MISSING = 1;
	const ERROR = 2;
}

class FH_Field {
	private $name;
	private $id;
	private $label;
	private $description;
	private $required = false;
	private $format;
	private $value;
	private $options;
	private $statuses;
	private $min = null;
	private $max = null;
	
	function is_numeric() {
		if (in_array($this->get_format(), Array(FH_Formats::PRICE, FH_Formats::CURRENCY, FH_Formats::INTEGER, FH_Formats::FLOAT))) {
			return true;
		}
		return false;
	}
	
	function set_name($vName) {
		$this->name = $vName;
	}
	
	function get_name() {
		return $this->name;
	}
	
	function set_id($vId) {
		$this->id = $vId;
	}
	
	function get_id() {
		return $this->id;
	}
	
	function set_label($vLabel) {
		$this->label = $vLabel;
	}
	
	function get_label() {
		return $this->label;
	}
	
	function set_description($vDescription) {
		$this->description = $vDescription;
	}
	
	function get_description() {
		return $this->description;
	}
	
	function set_min($vMin) {
		$this->min = $vMin;
	}
	
	function get_min() {
		return $this->min;
	}
	
	function set_max($vMax) {
		$this->max = $vMax;
	}
	
	function get_max() {
		return $this->max;
	}
	
	function set_required($vRequired) {
		$this->required = ($vRequired == true);
	}
	
	function is_required() {
		return $this->required == true;
	}
	
	function set_format($vFormat) {
		$this->format = $vFormat;
	}
	
	function get_format() {
		return $this->format;
	}
	
	function set_value($vValue) {
		$this->value = $vValue;
	}
	
	function get_value() {
		return $this->value;
	}
	
	function set_options($vOptions) {
		$this->options = $vOptions;
	}
	
	function get_options() {
		return $this->options;
	}
	
	function get_formatted_value() {
		if (is_null($this->get_value()) or $this->get_value() == '') {
			return '';
		} else {
			if ($this->get_format() == FH_Formats::PRICE) {
				return number_format($this->get_value(), 2);
			} else if ($this->get_format() == FH_Formats::CURRENCY) {
				$value = money_format($this->get_value());
			}
		}
		return $this->get_value();
	}
	
	function add_status($vStatus) {
		if (!in_array($vStatus, $this->statuses)) {
			array_push($this->statuses, $vStatus);
		}
	}
	
	function remove_status($vStatus) {
		if (not(in_array($vStatus, $this->statuses))) {
			$new_statuses = Array();
			foreach ($this->statuses as $status) {
				if ($status != $vStatus) {
					array_push($new_statuses, $status);
				}
				unset($this->statuses);
				$this->statuses = $new_statuses;
			}
		}
	}
	
	function has_status($vStatus) {
		return in_array($vStatus, $this->statuses);
	}
	
	function get_html_class() {
		$html = "";
		foreach ($this->statuses as $status) {
			if ($status == FH_Statuses::MISSING) {
				$html .= " missing";
			} else if ($status == FH_Statuses::ERROR) {
				$html .= " error";
			}
		}
		return $html;
	}
	
	function get_html_label() {
		$html = "<label class=\"" . $this->get_html_class() . "\">" . $this->get_label();
		if ($this->is_required()) {
			$html .= " *";
		}
		$html .= "</label>";
		return $html;
	}
	
	function get_field_type() {
		if ($this->format == FH_Formats::BOOLEAN) {
			return "checkbox";
		} else if ($this->format == FH_Formats::SELECT) {
			return "select";
		} else if ($this->format == FH_Formats::HIDDEN) {
			return "hidden";
		} else if ($this->format == FH_Formats::TEXTAREA) {
			return "textarea";
		} else {
			return "text";
		}
	}
	
	function get_html_field($attributes = Array()) {
		$html = "";
		if ($this->get_field_type() == "textarea") {
			$html .= "<textarea ";
		} else if ($this->get_field_type() == "select") {
			$html .= "<select ";
		} else {
			$html .= "<input type=\"" . $this->get_field_type() . "\" ";
		}
		$html .=
			"name=\"" . $this->get_name() . "\" " .
			"id=\"" . $this->get_id() . "\" " .
			"class=\"" . $attributes["class"] . $this->get_html_class() . "\" ";
		if ($this->get_field_type() != "textarea") {
			$html .= "value=\"" . htmlspecialchars($this->get_formatted_value()) ."\"";
		}
		if ($this->get_field_type() == "checkbox") {
			if ($this->get_value() == true) {
				$html .= "checked=\"checked\" ";
			}
		}
		if ($this->get_field_type() != "hidden") {
			foreach ($attributes as $attr => $value) {
				if (!in_array($attr, Array("class", "name", "id", "value"))) {
					$html .= "\"$attr\"=\"" . htmlspecialchars($value) . "\" ";
				}
			}
		}
		if ($this->get_field_type() == "select") {
			foreach ($attributes as $attr => $value) {
				if (!in_array($attr, Array("class", "name", "id", "value"))) {
					$html .= "\"$attr\"=\"" . htmlspecialchars($value) . "\" ";
				}
			}
		}
		if ($this->get_field_type() == 'textarea') {
			$html .= ">" . $this->get_formatted_value() . "</textarea>";
		} else if ($this->get_field_type() == "select") {
			$html .= ">";
			foreach ($this->get_options() as $option) {
				$html .= "<option value=\"" . $option[1] . "\"";
				if ($option[1] == $this->get_value()) {
					$html .= " selected=\"selected\"";
				}
				$html .= ">" . $option[0] . "</option>";
			}
			$html .= "</select>";
		} else {
			$html .= "/>";
		}
		return $html;
	}
	
	function __construct($vName, $vLabel, $properties = Array()) {
		$this->statuses = Array();
		$this->set_name($vName);
		$this->set_id($vName);
		$this->set_label($vLabel);
		if (isset($properties["required"])) {
			$this->set_required($properties["required"]);
		}
		if (isset($properties["format"])) {
			$this->set_format($properties["format"]);
		}
		if (isset($properties["value"])) {
			$this->set_value($properties["value"]);
		}
		if (isset($properties["options"])) {
			$this->set_options($properties["options"]);
		}
		if (isset($properties["description"])) {
			$this->set_description($properties["description"]);
		}
		if (isset($properties["min"])) {
			$this->set_min($properties["min"]);
		} else {
			$this->set_min(null); 
		}
		if (isset($properties["max"])) {
			$this->set_max($properties["max"]);
		} else {
			$this->set_max(null); 
		}
	}
	

}
?>