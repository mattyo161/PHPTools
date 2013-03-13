<?php 
# track if the fix POST slashes has been used yet or not
$fixed_POST_slashes = false;

/* This functino will make sure that the $_POST variable is updated based on the get_magic_quotes_gpc()
   function to determine if slashes should be stripped from the post or not
   Reference: http://www.apptools.com/phptools/forms/forms7.php
*/
function fix_POST_slashes() {
	global $fixed_POST_slashes;
	if (!$fixed_POST_slashes and get_magic_quotes_gpc()) {
		$fixed_POST_slashes = true;
		foreach ($_POST as $key => $val) {
			if (gettype($val) == "array") {
				foreach ($val as $i => $j) {
					$val[$i] = stripslashes($j);
				}
			} else {
				$_POST[$key] = stripslashes($val);
			}
		}
	}
}

class FH_Form {
	private $fields;
	private $messages;
	private $valid;
	private $message_on_labels;
	
	function __construct($vFields) {
		$this->fields = Array();
		$this->messages = Array();
		$this->valid = false;
		$this->message_on_labels = false;
		foreach ($vFields as $field) {
			$this->add($field);
		}
	}
	
	function add($vField) {
		array_push($this->fields, $vField);
		# create a back reference from the field back to the form
		$vField->set_form($this);
	}
	
	function field_by_name($name) {
		foreach ($this->fields as $field) {
			if ($field->get_name() == $name) {
				return $field;
			}
		}
	}
	
	function show_messages_on_labels() {
		$this->message_on_labels = true;
	}
	
	function hide_messages_on_labels() {
		$this->message_on_labels = false;
	}
	
	function get_messages_on_labels() {
		return $this->message_on_labels;
	}
	
	
	function field_by_id($id) {
		foreach ($this->fields as $field) {
			if ($field->get_id() == $id) {
				return $field;
			}
		}
	}
	
	function add_message($message, $field, $index = 0) {
		array_push($this->messages, new FH_Message($message, $field));
		$field->add_message($message, $index);
	}
	
	function get_messages() {
		return $this->messages;
	}
	
	function is_valid() {
		return $this->valid === true;
	}
	
	/**
	 * Load values for all the properties based on the provided hash
	 */
	function load_values($data = false) {
		if ($data === false) {
			fix_POST_slashes();
			$data = $_POST;
		}
		foreach ($this->fields as $field) {
			if ($field->get_multiple_values()) {
				$field_name = $field->get_var_name();
				if (isset($data[$field_name])) {
					$field->set_value($data[$field_name]);
				}
			} else {
				if (isset($data[$field->get_name()])) {
					$field->set_value($data[$field->get_name()]);
				}
			}
		}
	}
	
	/**
	 * Validate all data against $_POST by default or a passed array
	 */
	function validate($data = false) {
		if ($data === false) {
			fix_POST_slashes();
			$data = $_POST;
		}
		$this->valid = true;
		foreach ($this->fields as $field) {
			# make sure the field is not missing
			if ($field->is_required() and (!isset($data[$field->get_var_name()]) or $data[$field->get_var_name()] === "")) {
				# field is missing
				$field->add_status(FH_Statuses::MISSING);
				$this->add_message("Missing field " . $field->get_label(), $field);
				$this->valid = false;
			} else {
				$value = $data[$field->get_var_name()];
				# lets make sure the value is correct
				# some filters taken from
				# http://komunitasweb.com/2009/03/10-practical-php-regular-expression-recipes/
				
				# If this is a multiple value field then we need to test each value
				if ($field->get_multiple_values() and gettype($value) == "array") {
					# need to loop through the values
					for ($i = 0; $i < sizeof($value); $i++) {
						$subvalue = $value[$i];
						if ($field->is_required() and $subvalue === "") {
							# field is missing
							$field->add_status(FH_Statuses::MISSING, $i);
							$this->add_message("Missing field " . $field->get_label(), $field, $i);
							$this->valid = false;
						}
						if ($field->get_format() == FH_Formats::DATE) {
							if (preg_match("/^(\d{1,2})[\/\.\-](\d{1,2})[\/\.\-](\d{2,4})$/", $subvalue, $matches)) {
								$subvalue = $matches[1] . "/" . $matches[2] . "/" . $matches[3];
							} else {
								$field->add_status(FH_Statuses::ERROR, $i);
								$this->add_message("ERROR field " . $field->get_label() . " needs to be in format m/d/yyyy", $field, $i);
								$this->valid = false;
							}
						} else if ($field->get_format() == FH_Formats::PRICE) {
							$subvalue = floatval(preg_replace("/[^\-\d\.]/","", $subvalue));
							#$subvalue = number_format($subvalue, 2);
							# Having a value of 0 or less is not necessarily an invalid number, this should be checked by min max
							if (false and $value <= 0) {
								$field->add_status(FH_Statuses::ERROR, $i);
								$this->add_message("ERROR field " . $field->get_label() . " must be greater then 0", $field, $i);
								$this->valid = false;
							}
						} else if ($field->get_format() == FH_Formats::CURRENCY) {
							$subvalue = floatval(preg_replace("/[^\-\d\.]/","", $subvalue));
							$subvalue = money_format($subvalue,2);
							# Having a value of 0 or less is not necessarily an invalid number, this should be checked by min max
							if (false and $value <= 0) {
								$field->add_status(FH_Statuses::ERROR, $i);
								$this->add_message("ERROR field " . $field->get_label() . " must be greater then 0", $field, $i);
								$this->valid = false;
							}
						} else if ($field->get_format() == FH_Formats::STATE) {
							if (preg_match("/^\w\w$/", $subvalue, $matches)) {
								$subvalue = strtoupper($subvalue);
							} else {
								$field->add_status(FH_Statuses::ERROR, $i);
								$this->add_message("ERROR field " . $field->get_label() . " 2 letters", $field, $i);
								$this->valid = false;
							}
						} else if ($field->get_format() == FH_Formats::ZIP) {
							if (preg_match("/^(\d\d\d\d\d)/", $subvalue, $matches)) {
								$subvalue = $matches[1];
							} else {
								$field->add_status(FH_Statuses::ERROR, $i);
								$this->add_message("ERROR field " . $field->get_label() . " 5 digit zip code", $field, $i);
								$this->valid = false;
							}
						} else if ($field->get_format() == FH_Formats::EMAIL) {
							#if (eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $subvalue) {
							if (filter_var($subvalue, FILTER_VALIDATE_EMAIL)) {
								$subvalue = strtolower($subvalue);
							} else {
								$subvalue = strtolower(preg_replace("/[\s]/", "", $subvalue));
								$field->add_status(FH_Statuses::ERROR, $i);
								$this->add_message("ERROR field " . $field->get_label() . " must be a valid email", $field, $i);
								$this->valid = false;
							}
						} else if ($field->get_format() == FH_Formats::PHONE) {
							# OLD match string, need to have a better phone format function
							#if (preg_match('/\(?\d{3}\)?[-\s.]?\d{3}[-\s.]\d{4}/x', $subvalue)) {
							if (preg_match('/\d{3}-\d{3}-\d{4}/', $subvalue)) {
								$subvalue = strtolower($subvalue);
							} else {
								$tmpValue = strtolower(preg_replace("/[\s\D]/", "", $subvalue));
								if (preg_match('/^(\d{3})(\d{3})(\d{4})$/', $tmpValue, $match)) {
									$subvalue = $match[1] . "-" . $match[2] . "-" . $match[3];
								} else {
									$field->add_status(FH_Statuses::ERROR, $i);
									$this->add_message("ERROR field " . $field->get_label() . " must be a phone number in the form 999-999-9999", $field, $i);
									$this->valid = false;
								}
							}
						}
						if ($field->is_numeric()) {
							# we need to make sure that this is a number
							if (!is_numeric($subvalue)) {
								$field->add_status(FH_Statuses::ERROR, $i);
								$this->add_message("ERROR field " . $field->get_label() . " must be a number", $field, $i);
							}
							if (!is_null($field->get_min()) and $field->get_min() != '') { 
								if ($field->get_value() < $field->get_min()) {
									$field->add_status(FH_Statuses::ERROR, $i);
									$this->add_message("ERROR field " . $field->get_label() . " must be greater then or equal to " . $field->get_min(), $field, $i);
								}
							}
							if (!is_null($field->get_max()) and $field->get_min() != '') { 
								if ($field->get_value() > $field->get_max() and $field->get_max() != '') {
									$field->add_status(FH_Statuses::ERROR, $i);
									$this->add_message("ERROR field " . $field->get_label() . " must be less then or equal to " . $field->get_max(), $field, $i);
								}
							}
						}
						$value[$i] = $subvalue;
					}
					$field->set_value($value);
				} else {
					if ($field->get_format() == FH_Formats::DATE) {
						if (preg_match("/^(\d{1,2})[\/\.\-](\d{1,2})[\/\.\-](\d{2,4})$/", $value, $matches)) {
							$value = $matches[1] . "/" . $matches[2] . "/" . $matches[3];
						} else {
							$field->add_status(FH_Statuses::ERROR);
							$this->add_message("ERROR field " . $field->get_label() . " needs to be in format m/d/yyyy", $field);
							$this->valid = false;
						}
						$field->set_value($value);
					} else if ($field->get_format() == FH_Formats::PRICE) {
						$value = floatval(preg_replace("/[^\-\d\.]/","", $value));
						#$value = number_format($value, 2);
						# Having a value of 0 or less is not necessarily an invalid number, this should be checked by min max
						if (false and $value <= 0) {
							$field->add_status(FH_Statuses::ERROR);
							$this->add_message("ERROR field " . $field->get_label() . " must be greater then 0", $field);
							$this->valid = false;
						}
						$field->set_value($value);
					} else if ($field->get_format() == FH_Formats::CURRENCY) {
						$value = floatval(preg_replace("/[^\-\d\.]/","", $value));
						$value = money_format($value,2);
						if (false and $value <= 0) {
							$field->add_status(FH_Statuses::ERROR);
							$this->add_message("ERROR field " . $field->get_label() . " must be greater then 0", $field);
							$this->valid = false;
						}
						$field->set_value($value);
					} else if ($field->get_format() == FH_Formats::STATE) {
						if (preg_match("/^\w\w$/", $value, $matches)) {
							$value = strtoupper($value);
							$field->set_value($value);
						} else {
							$field->add_status(FH_Statuses::ERROR);
							$this->add_message("ERROR field " . $field->get_label() . " 2 letters", $field);
							$this->valid = false;
						}
					} else if ($field->get_format() == FH_Formats::ZIP) {
						if (preg_match("/^(\d\d\d\d\d)/", $value, $matches)) {
							$value = $matches[1];
							$field->set_value($value);
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
						$field->set_value($value);
					} else if ($field->get_format() == FH_Formats::PHONE) {
						# OLD match string, need to have a better phone format function
						#if (preg_match('/\(?\d{3}\)?[-\s.]?\d{3}[-\s.]\d{4}/x', $value)) {
						if (preg_match('/\d{3}-\d{3}-\d{4}/', $value)) {
							$value = strtolower($value);
						} else {
							$tmpValue = strtolower(preg_replace("/[\s\D]/", "", $value));
							if (preg_match('/^(\d{3})(\d{3})(\d{4})$/', $tmpValue, $match)) {
								$value = $match[1] . "-" . $match[2] . "-" . $match[3];
							} else {
								$field->add_status(FH_Statuses::ERROR);
								$this->add_message("ERROR field " . $field->get_label() . " must be a phone number in the form 999-999-9999", $field);
								$this->valid = false;
							}
						}
						$field->set_value($value);
					}
					if ($field->is_numeric()) {
						# we need to make sure that this is a number
						if (!is_numeric($value)) {
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
				}
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
	const RADIO = 19;
	const CHECKBOX = 20;
	const URL = 21;
	const WEBSITE = 22;
	const PASSWORD = 23;
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
	private $options = null;
	private $statuses;
	private $messages;
	private $min = null;
	private $max = null;
	private $multiple_values = false;
	private $form;
	
	function is_numeric() {
		if (in_array($this->get_format(), Array(FH_Formats::PRICE, FH_Formats::CURRENCY, FH_Formats::INTEGER, FH_Formats::FLOAT))) {
			return true;
		}
		return false;
	}
	
	function set_name($vName) {
		$this->name = $vName;
		if (preg_match('/\[\]$/', $vName)) {
			$this->set_multiple_values(true);
		}
	}
	
	function get_var_name() {
		if ($this->get_multiple_values()) {
			return preg_replace('/\[\]$/', '', $this->name);
		} else {
			return $this->name;
		}
	}
	
	function get_name() {
		return $this->name;
	}
	
	function set_form($vForm) {
		$this->form = $vForm;
	}
	
	function get_form($vForm) {
		return $this->form;
	}
	
	function set_id($vId) {
		$this->id = $this->fix_html_id($vId);
	}
	
	function get_id($params = Array()) {
		$newid = $this->id;
		if (sizeof($params) > 0 and gettype($params) == "array") {
			if (isset($params["prefix"])) {
				$newid = $this->fix_html_id($params["prefix"]) . "-" . $newid;
			}
			if (isset($params["suffix"])) {
				$newid .= "-" . $this->fix_html_id($params["suffix"]);
			}
			if (isset($params["index"])) {
				$newid .= "-" . $params["index"];
			}
		}
		return $newid;
	}
	
	function fix_html_id($vId) {
		$id =  preg_replace("/[^a-zA-Z0-9\_\-\.\:]/","",$vId);
		# must begin with letter
		return preg_replace("/^[^a-zA-Z]+/","", $id);
		
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
		$this->required = ($vRequired === true);
	}
	
	function is_required() {
		return $this->required === true;
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
	
	function get_value($params = Array()) {
		if (sizeof($params) > 0 and gettype($params) == "array") {
			if (isset($params["index"]) and gettype($this->value) == "array") {
				return $this->value[$params["index"]];
			}
		} elseif ($this->format == FH_Formats::BOOLEAN) {
			if ($this->value == "true") {
				return true;
			} else {
				return false;
			}
		}
		return $this->value;
	}
	
	function set_multiple_values($vValue) {
		$this->multiple_values = ($vValue === true);
	}
	
	function get_multiple_values() {
		return $this->multiple_values;
	}
	
	function get_sql_value() {
		return mysql_real_escape_string($this->value);
	}
	
	function set_options($vOptions) {
		$this->options = $vOptions;
	}
	
	function get_options() {
		return $this->options;
	}
	
	function get_formatted_value($params = Array()) {
		if (is_null($this->get_value($params)) or $this->get_value($params) === '') {
			return '';
		} else {
			if ($this->get_format() == FH_Formats::PRICE) {
				return number_format(floatval(preg_replace("/[^\-\d\.]/","", $this->get_value($params))), 2);
			} else if ($this->get_format() == FH_Formats::CURRENCY) {
				$value = money_format(floatval(preg_replace("/[^\-\d\.]/","", $this->get_value($params))),2);
			}
		}
		return $this->get_value($params);
	}
	
	function add_status($vStatus, $index = 0) {
		# check for multiple values
		if ($this->get_multiple_values()) {
			if (!isset($this->statuses[$index])) {
				$this->statuses[$index] = Array();
			}	
			if (!in_array($vStatus, $this->statuses[$index])) {
				array_push($this->statuses[$index], $vStatus);
			}
		} else {
			if (!in_array($vStatus, $this->statuses)) {
				array_push($this->statuses, $vStatus);
			}
		}
	}
	
	function add_message($vMessage, $index = 0) {
		# check for multiple values
		if ($this->get_multiple_values()) {
			if (!isset($this->messages[$index])) {
				$this->messages[$index] = Array();
			}	
			array_push($this->messages[$index], $vMessage);
		} else {
			array_push($this->messages, $vMessage);
		}
	}

	function get_messages($index = 0) {
		# check for multiple values
		if ($this->get_multiple_values()) {
			if (!isset($this->messages[$index])) {
				return Array();
			} else {
				return $this->messages[$index];
			}
		} else {
			$this->messages;
		}
	}

	
	function remove_status($vStatus, $index = 0) {
		if ($this->get_multiple_values()) {
			if (in_array($vStatus, $this->statuses[$index])) {
				$new_statuses = Array();
				foreach ($this->statuses as $status) {
					if ($status != $vStatus) {
						array_push($new_statuses, $status);
					}
					$this->statuses[$index] = $new_statuses;
				}
			}
		} else {
			if (in_array($vStatus, $this->statuses)) {
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
	}
	
	function has_status($vStatus, $index = 0) {
		if ($this->get_multiple_values()) {
			if (isset($this->statuses[$index])) {
				return in_array($vStatus, $this->statuses[$index]);
			} else {
				return false;
			}
		} else {
			return in_array($vStatus, $this->statuses);
		}
	}
	
	function get_html_class($params = Array()) {
		$html = "";
		if ($this->get_multiple_values()) {
			if (!isset($params["index"])) {
				# check statuses for all entries
				$statuses = Array();
				foreach ($this->statuses as $index => $value) {
					foreach ($value as $status) {
						if ($status == FH_Statuses::MISSING and !in_array($status, $statuses)) {
							$html .= " missing";
							array_push($statuses, $status);
						} else if ($status == FH_Statuses::ERROR and !in_array($status, $statuses)) {
							$html .= " error";
							array_push($statuses, $status);
						}
					}
				}
			} else {
				$index = $params["index"];
				if (isset($this->statuses[$index])) {
					foreach ($this->statuses[$index] as $status) {
						if ($status == FH_Statuses::MISSING) {
							$html .= " missing";
						} else if ($status == FH_Statuses::ERROR) {
							$html .= " error";
						}
					}
				}
			}
		} else {
			foreach ($this->statuses as $status) {
				if ($status == FH_Statuses::MISSING) {
					$html .= " missing";
				} else if ($status == FH_Statuses::ERROR) {
					$html .= " error";
				}
			}
		}
		# lets add the name of the field to the class
		$html .= " fieldname-" . strtolower(preg_replace("/\W/", '', $this->name));
		if ($this->format == FH_Formats::TEXT) {
			$html .= " field-text";
		} else if ($this->format == FH_Formats::DATE) {
			$html .= " field-date";
		} else if ($this->format == FH_Formats::TIME) {
			$html .= " field-time";
		} else if ($this->format == FH_Formats::DATETIME) {
			$html .= " field-datetime";
		} else if ($this->format == FH_Formats::PRICE) {
			$html .= " field-price";
		} else if ($this->format == FH_Formats::BOOLEAN) {
			$html .= " field-boolean";
		} else if ($this->format == FH_Formats::CURRENCY) {
			$html .= " field-currency";
		} else if ($this->format == FH_Formats::HIDDEN) {
			$html .= " field-hidden";
		} else if ($this->format == FH_Formats::STATE) {
			$html .= " field-state";
		} else if ($this->format == FH_Formats::ZIP) {
			$html .= " field-zip";
		} else if ($this->format == FH_Formats::EMAIL) {
			$html .= " field-email";
		} else if ($this->format == FH_Formats::CC) {
			$html .= " field-cc";
		} else if ($this->format == FH_Formats::CCCODE) {
			$html .= " field-cccode";
		} else if ($this->format == FH_Formats::PHONE) {
			$html .= " field-phone";
		} else if ($this->format == FH_Formats::INTEGER) {
			$html .= " field-integer";
		} else if ($this->format == FH_Formats::TEXTAREA) {
			$html .= " field-textarea";
		} else if ($this->format == FH_Formats::FLOAT) {
			$html .= " field-float";
		} else if ($this->format == FH_Formats::SELECT) {
			$html .= " field-select";
		} else if ($this->format == FH_Formats::RADIO) {
			$html .= " field-radio";
		} else if ($this->format == FH_Formats::CHECKBOX) {
			$html .= " field-checkbox";
		} else if ($this->format == FH_Formats::URL) {
			$html .= " field-url";
		} else if ($this->format == FH_Formats::WEBSITE) {
			$html .= " field-website";
		} else if ($this->format == FH_Formats::PASSWORD) {
			$html .= " field-password";
		}
		
		return $html;
	}
	
	function get_html_label($attributes = Array()) {
		$html = "<label for=\"" . $this->get_name() . "\" class=\"" . $attributes["class"] . $this->get_html_class() . "\"";
		foreach ($attributes as $attr => $value) {
			if (!in_array($attr, Array("class", "name", "id", "value", "index"))) {
				$html .= "$attr=\"" . htmlspecialchars($value) . "\" ";
			}
		}
		$html .= ">" . $this->get_label();
		if ($this->is_required()) {
			$html .= " *";
		}
		if (sizeof($this->messages) > 0 and $this->form->get_messages_on_labels() ) {
			$html .= " (" . join(", ", $this->messages) . ")";
		}
		
		$html .= "</label>";
		return $html;
	}
	
	function get_field_type() {
		if ($this->format == FH_Formats::BOOLEAN) {
			return "boolean";
		} else if ($this->format == FH_Formats::SELECT) {
			return "select";
		} else if ($this->format == FH_Formats::RADIO) {
			return "radio";
		} else if ($this->format == FH_Formats::CHECKBOX) {
			return "checkbox";
		} else if ($this->format == FH_Formats::HIDDEN) {
			return "hidden";
		} else if ($this->format == FH_Formats::TEXTAREA) {
			return "textarea";
		} else if ($this->format == FH_Formats::PASSWORD) {
			return "password";
		} else {
			return "text";
		}
	}
	
	function get_html_field($attributes = Array(), $index = null) {
		$html = "";
		# if this is a checkbox or radio object with options then we need to handle it complete differnetly.
		if (($this->get_field_type() == "checkbox" or $this->get_field_type() == "radio") and $this->get_options() != null ) {
			$options = $this->get_options();
			if (!is_null($index)) {
				$options = Array($options[$index]);
			}
			foreach ($options as $option) {
				# we need to put a span wrapper around each entry, this allows custom styling of each entry, like treating them like blocks or whatever this is also where ID will be applied
				if (gettype($option) == "array") {
					$option_label = $option[0];
					$option_value = $option[1];
				} else {
					$option_label = $option;
					$option_value = $option;
				}
				$html .= "<span " .
							"id=\"" . $this->get_id(Array("suffix" => $option_value)) . "\" " .
							"class=\"" . $attributes["class"] . $this->get_html_class() . "\">\n";
					if ($this->get_field_type() == "checkbox") {
						$html .= "\t<input type=\"" . $this->get_field_type() . "\" " .
									"name=\"" . $this->get_name() . "[]\" " .
									"value=\"" . $option_value . "\" ";
					} else {
						$html .= "\t<input type=\"" . $this->get_field_type() . "\" " .
									"name=\"" . $this->get_name() . "\" " .
									"value=\"" . $option_value . "\" ";
					}
					if (gettype($this->get_value()) == "array") {
						foreach ($this->get_value() as $curr_value) {
							if (strtolower($option_value) == strtolower($curr_value)) {
								$html .= " checked=\"checked\"";
							}
						}
					} else {
						if (strtolower($option_value) == strtolower($this->get_value())) {
							$html .= " checked=\"checked\"";
						}
					}
					foreach ($attributes as $attr => $value) {
						if (!in_array($attr, Array("class", "name", "id", "value", "index"))) {
							$html .= "$attr=\"" . htmlspecialchars($value) . "\" ";
						}
					}
					$html .= ">\n";
					# now we insert the label for the input
					$html .= "\t<span>" . $option_label . "</span>\n";
				$html .= "</span>\n";
			}
		} else {
			if ($this->get_field_type() == "textarea") {
				$html .= "<textarea ";
			} else if ($this->get_field_type() == "select") {
				$html .= "<select ";
			} else if ($this->get_field_type() == "boolean") {
				$html .= "<input type=\"checkbox\" value=\"true\" ";
			} else {
				$html .= "<input type=\"" . $this->get_field_type() . "\" ";
			}
			if ($this->get_multiple_values() and isset($attributes["index"])) {
				$html .=
					"name=\"" . $this->get_name() . "\" " .
					"id=\"" . $this->get_id(Array("index" => $attributes["index"])) . "\" " .
					"class=\"" . $attributes["class"] . $this->get_html_class(Array("index" => $attributes["index"])) . "\" ";
				if ($this->get_field_type() != "textarea") {
					$html .= "value=\"" . htmlspecialchars($this->get_formatted_value(Array("index" => $attributes["index"]))) ."\"";
				}
			} else {
				$html .=
					"name=\"" . $this->get_name() . "\" " .
					"id=\"" . $this->get_id() . "\" " .
					"class=\"" . $attributes["class"] . $this->get_html_class() . "\" ";
				if ($this->get_field_type() != "textarea") {
					$html .= "value=\"" . htmlspecialchars($this->get_formatted_value()) ."\"";
				}
			}
			if ($this->get_field_type() == "boolean") {
				if ($this->get_value() == true) {
					$html .= "checked=\"checked\" ";
				}
			}
			if ($this->get_field_type() != "hidden") {
				foreach ($attributes as $attr => $value) {
					if (!in_array($attr, Array("class", "name", "id", "value", "index"))) {
						$html .= "$attr=\"" . htmlspecialchars($value) . "\" ";
					}
				}
			}
			if ($this->get_field_type() == 'textarea') {
				$html .= ">" . $this->get_formatted_value() . "</textarea>";
			} else if ($this->get_field_type() == "select") {
				$html .= ">";
				foreach ($this->get_options() as $option) {
					# check to see if the $option is an array if so then first item is the value second is the description
					if (gettype($option) == "array") {
						$html .= "<option value=\"" . $option[1] . "\"";
						if (strtolower($option[1]) == strtolower($this->get_value())) {
							$html .= " selected=\"selected\"";
						}
						$html .= ">" . $option[0] . "</option>";
					} else {
						$html .= "<option value=\"" . $option . "\"";
						if (strtolower($option) == strtolower($this->get_value())) {
							$html .= " selected=\"selected\"";
						}
						$html .= ">" . $option . "</option>";
					}
				}
				$html .= "</select>";
			} else {
				$html .= "/>";
			}
		}
		return $html;
	}
	
	function __construct($vName, $vLabel, $properties = Array()) {
		$this->statuses = Array();
		$this->messages = Array();
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
		if (isset($properties["default"])) {
			$this->set_value($properties["default"]);
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
