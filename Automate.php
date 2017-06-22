<?php



interface AutomateData
{
	public function saveDeviceData ($device_type, $device_name, $data);
	public function saveOutletSensorlist ($device_name, $sensor_broadcast);

//	public function getIAmHereData ($device_name);
	public function getDeviceConfigData ($device_name);
	public function getSensorDeviceData ($device_name);
	public function getOutletDeviceData ($device_name);

	public function getOutletCount ($device_name);
	public function getOutletSensorlistData ($device_name);
//	public function getOutletStateData ($device_name);
	public function getOutletConfigData ($device_name, $first_outlet_id, $count);
	public function getOutletRulesData ($device_name, $outlet_id, $first_rule_id, $count);

	public function saveSensorMetricData ($device_name, $sensor_broadcast);
	public function getSensorMetricData ($device_name);

	public function saveRequestData ($device_name, $query_vars, $request);
	public function saveResponseData ($device_name, $response_id, $response);
	public function getRequestData ($device_name);
	public function getResponseData ($device_name, $response);
}


class FileData implements AutomateData
{
	public static $I_AM_HERE_SIZE						= 8+4+4+128+16;

	public static $DEVICE_CONFIG_SIZE					= 8+4+4+128+8+4+16+2+128+128;

	public static $SENSOR_DEVICE_SIZE					= 8012;

	public static $OUTLET_DEVICE_SIZE					= 8+128+4;
	public static $OUTLET_CONFIG_SIZE					= 2+2+4+128;
	public static $OUTLET_STATE_SIZE					= 4+4;
	public static $OUTLET_COUNT_MAX						= 32;

	public static $controller_data_mode					= 0777;

	public static $controller_data_dir					= "data";
	public static $device_subdir						= "device";
	public static $outlet_subdir						= "outlet";
	public static $sensor_subdir						= "sensor";
	public static $talk_subdir							= "talk";

	public static $controller_file_device				= "device";
	public static $controller_file_metric				= "metric";
	public static $controller_file_i_am_here			= "device.iamhere";
	public static $controller_file_device_config		= "device.config";
	public static $controller_file_sensor_device		= "device.sensor";
	public static $controller_file_outlet_device		= "device.outlet";
	public static $controller_file_outlet_sensorlist	= "device.sensorlist";
	public static $controller_file_outlet_state			= "state";
	public static $controller_file_outlet_config_ext	= ".outlet";
	public static $controller_file_rules_ext			= ".rules";
	public static $controller_file_request_ext			= ".request";
	public static $controller_file_response_ext			= ".response";



	/**
	 *	@brief
	 *			Miscellaneous supported functions
	 *			for access to files and to get
	 *			file path
	 */

	private static function fopen_lock ($file, $mode = "r+b", $lock_mask = LOCK_EX)
	{
		if (!$file || $file == "") return FALSE;
		$fd = fopen($file, $mode);
		if ($fd) {
			if (flock($fd, $lock_mask)) {
				return $fd;
			}
			else {
				fclose($fd);
			}
		}
		return FALSE;
	}

	private static function funlock_close ($fd)
	{
		if (!$fd) return FALSE;
		flock($fd, LOCK_UN);
		fclose($fd);
		return TRUE;
	}

	private static function get_file ($dir, $filename)
	{
		if (!file_exists($dir) || !is_dir($dir)) {
			return NULL;
		}

		$file = $dir . "/" . $filename;
		if (!file_exists($file)) {
			return NULL;
		}

		$fd = fopen($file, "rb");
		if (!$fd) {
			return NULL;
		}

		$result = NULL;

		$stat = fstat($fd);
		if ($stat['size'] > 0) {

			$result = fread($fd, $stat['size']);
		}
		else {
			$result = NULL;
		}

		fclose($fd);

		return $result;
	}


	private static function get_device_dir ($device_name)
	{
		$device_dir = self::$controller_data_dir . "/" . $device_name;
		if (!file_exists($device_dir)) {
			if (!mkdir($device_dir, self::$controller_data_mode, TRUE)) {
				echo "cannot create directory \"" . $device_dir . "\"\n";
			}
		}
		return $device_dir;
	}
	private static function get_device_subdir ($device_name)
	{
		$dir = self::$controller_data_dir . "/" . $device_name . "/" . self::$device_subdir;
		if (!file_exists($dir)) {
			if (!mkdir($dir, self::$controller_data_mode, TRUE)) {
				echo "cannot create directory \"" . $dir . "\"\n";
			}
		}
		return $dir;
	}
	private static function get_outlet_subdir ($device_name)
	{
		$dir = self::$controller_data_dir . "/" . $device_name . "/" . self::$outlet_subdir;
		if (!file_exists($dir)) {
			if (!mkdir($dir, self::$controller_data_mode, TRUE)) {
				echo "cannot create directory \"" . $dir . "\"\n";
			}
		}
		return $dir;
	}
	private static function get_sensor_subdir ($device_name)
	{
		$dir = self::$controller_data_dir . "/" . $device_name . "/" . self::$sensor_subdir;
		if (!file_exists($dir)) {
			if (!mkdir($dir, self::$controller_data_mode, TRUE)) {
				echo "cannot create directory \"" . $dir . "\"\n";
			}
		}
		return $dir;
	}
	private static function get_talk_subdir ($device_name)
	{
		$dir = self::$controller_data_dir . "/" . $device_name . "/" . self::$talk_subdir;
		if (!file_exists($dir)) {
			if (!mkdir($dir, self::$controller_data_mode, TRUE)) {
				echo "cannot create directory \"" . $dir . "\"\n";
			}
		}
		return $dir;
	}


/*
	private function update_device_config ($device_name)
	{
		$dir = self::get_device_subdir($device_name);
		if (!$dir) return;

		$file = $dir . "/" . self::$controller_file_device;
		unlink($file);
	}
*/




	public function __construct ()
	{
		if (!file_exists(self::$controller_data_dir)) {
			if (!mkdir(self::$controller_data_dir, self::$controller_data_mode, TRUE)) {
				echo "cannot create directory \"" . self::$controller_data_dir . "\"\n";
			}
		}
	}
	public function __destruct ()
	{
	}






	/**
	 *	@brief
	 *			Save all device data and break it to some usable parts
	 *
	 *	@param
	 *			$device_type - uint32_t flags that describe device type (such as DEVICE_SENSOR, DEVICE_OUTLET etc. see protocol.h)
	 *			$device_name - uint8_t[8] device_id that destinguish one device from another
	 *			$data - structure flow (array) that use as descriptor of the device
	 *
	 */
	public function saveDeviceData ($device_type, $device_name, $data)
	{
		$device_dir = self::get_device_subdir($device_name);
		$filename = $device_dir . "/" . self::$controller_file_device;

		file_put_contents($filename, $data);

		$fd = fopen($device_dir . "/" . self::$controller_file_device, "rb");
		if (!$fd) {
			return FALSE;
		}

		// save I_AM_HERE
		$filename = $device_dir . "/" . self::$controller_file_i_am_here;

		$i_am_here = fread($fd, self::$I_AM_HERE_SIZE); // 8+4+4+128+16
		file_put_contents($filename, $i_am_here);


		if ($device_type & Automate::$DEVICE_TYPE_OUTLET) {

			// save OUTLET_DEVICE
			$filename = $device_dir . "/" . self::$controller_file_outlet_device;

			$outdev_conf = fread($fd, self::$OUTLET_DEVICE_SIZE); // 8+128+4
			file_put_contents($filename, $outdev_conf);

			$outlet_dir = self::get_outlet_subdir($device_name);

			// save OUTLET_CONFIG
			$outlet_count = fread($fd, 4);
			$count_unpacked = unpack("V", $outlet_count);
			$outlet_count = ($count_unpacked && $count_unpacked[1] <= self::$OUTLET_COUNT_MAX)? $count_unpacked[1] : 0;

			for ($i=0; $i<$outlet_count; $i++) {
				$outlet_name = sprintf("%02d", $i);
				$filename = $outlet_dir . "/" . $outlet_name . self::$controller_file_outlet_config_ext;

				$outlet_config = fread($fd, self::$OUTLET_CONFIG_SIZE);
				file_put_contents($filename, $outlet_config);

				// remove rule file (if exists) to be updated.
				$rulefile = $outlet_dir . "/" . $outlet_name . self::$controller_file_rules_ext;

				if (file_exists($rulefile)) {
					unlink($rulefile);
				}
			}
		}

		if ($device_type & Automate::$DEVICE_TYPE_SENSOR) {

			// save SENSOR_DEVICE
			$filename = $device_dir . "/" . self::$controller_file_sensor_device;

			$sensor_device = fread($fd, self::$SENSOR_DEVICE_SIZE);
			file_put_contents($filename, $sensor_device);
		}


		// save DEVICE_CONFIG
		$filename = $device_dir . "/" . self::$controller_file_device_config;

		$device_config = fread($fd, self::$DEVICE_CONFIG_SIZE);
		file_put_contents($filename, $device_config);

		// save the rest of data (if it's size > 0 means mistake in data format)
		$filename = $device_dir . "/" . "therest";

		$therest = fread($fd, 100);
		file_put_contents($filename, $therest);

		fclose($fd);

		return TRUE;
	}



	/**
	 *	@brief
	 *			Sensor list of the device. If device type has DEVICE_OUTLET flag is set it is listening to sensors around
	 *			and save their metrics for using as orientir. This function tells us list of sensors discovered by the device
	 *
	 *	@param
	 *			$device_name - uint8_t[8] device_id (identificator of the device (MAC padded))
	 *			$sensor_broadcast - sensor_broadcast structures flow (one or more) that the device has heared
	 *
	 */
	public function saveOutletSensorlist ($device_name, $sensor_broadcast)
	{
		$device_dir = self::get_device_subdir($device_id);
		$file = $device_dir . "/" . self::$controller_file_outlet_sensorlist;
		file_put_contents($file, $sensor_broadcast);

		return TRUE;
	}


	/**
	 *	@brief
	 *			Save rules of defined outlet
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *			$outlet_id - outlet that rules flow respect to
	 *			$outlet_rules - rules flow (array of outlet_rule structure (see protocol.h))
	 *
	 */
	public function saveOutletRules ($device_name, $outlet_id, $outlet_rules)
	{
		$outlet_name = sprintf("%02d", $outlet_id);

		$outlet_dir = self::get_outlet_subdir($device_name);
		$file = $outlet_dir . "/" . $outlet_name . self::$controller_file_rules_ext;

		if (file_exists($file)) {
			unlink($file);
		}

		file_put_contents($file, $outlet_rules);

		return TRUE;
	}


	/**
	 *	@brief
	 *			Save defined device states of outlets.
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *			$outlet_state - outlet_state structures flow (array)
	 *
	 */
	public function saveOutletStates ($device_name, $outlet_state)
	{
		$outlet_dir = self::get_outlet_subdir($device_name);
		$file = $outlet_dir . "/" . self::$controller_file_outlet_state;

		file_put_contents($file, $outlet_state);

		return TRUE;
	}



	/**
	 *	@brief
	 *			Save local metric of sensor device.
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *			$sensor_broadcast - sensor_broadcast structure (see protocol.h)
	 *
	 */
	public function saveSensorMetricData($device_name, $sensor_broadcast)
	{
		$sensor_dir = self::get_sensor_subdir($device_name);
		$file = $sensor_dir . "/" . self::$controller_file_metric;

		file_put_contents($file, $sensor_broadcast);

		return TRUE;
	}


	/**
	 *	@brief
	 *			This function used by client to get response (with response ID) from the device
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *			$response_id - response identifier returned by server on request (earlier sent)
	 *
	 */
	public function getResponseData ($device_name, $response_id)
	{
		$response_data = "";

		$talk_dir = self::get_talk_subdir($device_name);
		if (!file_exists($talk_dir) || !is_dir($talk_dir)) {
			return $response_data;
		}

		$response_file = $talk_dir . "/" . sprintf("%08d", $response_id) . self::$controller_file_response_ext;

		if (file_exists($response_file) && is_file($response_file)) {

			$fd = self::fopen_lock($response_file, "r+b", LOCK_EX);
			if (!$fd) {
				return $response_data;
			}

			$stat = fstat($fd);
			if ($stat['size'] > 0) {
				$req = fread($fd, $stat['size']);
				$response_data = $req;
			}
			else {
				$response_data = pack("V", 0xFFFFFFFF);
			}

			self::funlock_close($fd);
			unlink($response_file);
		}

		return $response_data;
	}


	/**
	 *	@brief
	 *			Save request for the device. Function returns response ID which will be used to get response later.
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *			$query_vars - query variables that take part in request
	 *			$request_data - body of the request (if needed)
	 *
	 */
	public function saveRequestData ($device_name, $query_vars, $request_data)
	{
		$talk_dir = self::get_talk_subdir($device_name);
		if (!file_exists($talk_dir) || !is_dir($talk_dir)) {
			return FALSE;
		}

		$device_requests = glob($talk_dir . "/*" . self::$controller_file_request_ext);
		$device_responses = glob($talk_dir . "/*" . self::$controller_file_response_ext);

		if ($device_requests === FALSE || $device_responses === FALSE) {
			return FALSE;
		}


		// the first file (initialization)

		$request_id = 1;
		$response_id = 1;

		if (count($device_requests) > 0) {
			// get the last request
			$request_id = (intval(strtok(basename($device_requests[count($device_requests)-1]), self::$controller_file_request_ext)) +1);
		}
		if (count($device_responses) > 0) {
			// get the last response
			$response_id = (intval(strtok(basename($device_responses[count($device_responses)-1]), self::$controller_file_response_ext)) +1);
		}
		$request_id = ($request_id > $response_id)? $request_id : $response_id;



		// write new request file

		$requestfile = $talk_dir . "/" . sprintf("%08d", $request_id) . self::$controller_file_request_ext;

		$fd = self::fopen_lock($requestfile, "w+b", LOCK_EX);
		if (!$fd) return FALSE;


		// format request
		$request = $_SERVER['REQUEST_METHOD'] . " /" . $query_vars['cmd'];
		$request .= "?request=" . $request_id;

		foreach ($query_vars as $key => $value) {
			if ($key !== 'cmd' && $key != 'q') {
				$request .= "&" . $key . "=" . $value;
			}
		}
		$request .= "\r\n\r\n";


		if (!fwrite($fd, $request)) {
			self::funlock_close($fd);
			return FALSE;
		}


		if ($request_data) {
			if (!fwrite($fd, $request_data)) {
				self::funlock_close($fd);
				return FALSE;
			}
		}

		self::funlock_close($fd);

		return $request_id;
	}



	/**
	 *	@brief
	 *			Device use this function for save response with defined ID (for client)
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *			$response_id - response identifier
	 *			$response_data - status code or appropriate data for defined request
	 *
	 */
	public function saveResponseData ($device_name, $response_id, $response_data)
	{
		$talk_dir = self::get_talk_subdir($device_name);
		if (!file_exists($talk_dir) || !is_dir($talk_dir)) {
			return FALSE;
		}

		$responsefile = $talk_dir . "/" . sprintf("%08d", $response_id) . self::$controller_file_response_ext;

		if (!$response_data) {
			return FALSE;
		}

		$fd = $this->fopen_lock($responsefile, "w+b", LOCK_EX);
		if (!$fd) {
			return FALSE;
		}

		if (!fwrite($fd, $response_data)) {
			$this->funlock_close($fd);
			return FALSE;
		}

		$this->funlock_close($fd);

		return TRUE;
	}


	/**
	 *	@brief
	 *			Check if the device is registered on the gateway
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *
	 */
	public function isDeviceData ($device_name)
	{
		$device_dir = self::get_device_subdir($device_name);
		if (!file_exists($device_dir) || !is_dir($device_dir)) {
			return FALSE;
		}
		$device_file = $device_dir . "/" . self::$controller_file_device;
		if (!file_exists($device_file) || !is_file($device_file)) {
			return FALSE;
		}

		return TRUE;
	}


	/**
	 *	@brief
	 *			Device use this function to get request (with request ID)
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *
	 */
	public function getRequestData ($device_name)
	{
		$talk_dir = self::get_talk_subdir($device_name);
		if (!file_exists($talk_dir) || !is_dir($talk_dir)) {
			return FALSE;
		}

		$request_data = "";

		// get all device requests

		$device_requests = glob($talk_dir . "/*" . self::$controller_file_request_ext);

		if ($device_requests === FALSE) {
			return FALSE;
		}

		if (count($device_requests) == 0) {
			// no any requests in the mailbox; return success.
			return $request_data;
		}

		// get the first of requests

		$file = $device_requests[0];
		if (!file_exists($file)) {
			return $request_data;
		}

		$fd = $this->fopen_lock($file, "r+b", LOCK_EX);
		if (!$fd) {
			return FALSE;
		}

		$stat = fstat($fd);
		if ($stat['size'] > 0) {
			$request_data = fread($fd, $stat['size']);
		}

		$this->funlock_close($fd);
		unlink($file);

		return $request_data;
	}


	/**
	 *	@brief
	 *			Client use this function to read actual metric from device local sensors
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *
	 */
	public function getSensorMetricData ($device_name)
	{
		$sensor_metric = self::get_file(self::get_sensor_subdir($device_name), self::$controller_file_metric);
		if (!$sensor_metric) {
			return pack("V", 0xFFFFFFFF);
		}
		return $sensor_metric;
	}

	/**
	 *	@brief
	 *			Function returns device sensor configuration. Name, ADC pins, Logic pins, Digital (I2C) sensors and their names.
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *
	 */
	public function getSensorDeviceData ($device_name)
	{
		$sensor_device = self::get_file(self::get_device_subdir($device_name), self::$controller_file_sensor_device);
		if (!$sensor_device) {
			return pack("V", 0xFFFFFFFF);
		}
		return $sensor_device;
	}

	/**
	 *	@brief
	 *			Common device configuration (device_config structure). See protocol.h for more info
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *
	 */
	public function getDeviceConfigData ($device_name)
	{
		$device_config = self::get_file(self::get_device_subdir($device_name), self::$controller_file_device_config);
		if (!$device_config) {
			return pack("V", 0xFFFFFFFF);
		}
		return $device_config;
	}

	/**
	 *	@brief
	 *			Get rules of $outlet_id from registered device.
	 *			Rules in range from $first_rule_id (ae then 0) to ($first_rule_id + $count).
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *			$outlet_id - outlet order number we interesting in
	 *			$first_rule_id - the first rule to begin outlet_rule flow
	 *			$count - count of rules we needed (usually 100 to get all of rules on the outlet are)
	 *
	 */
	public function getOutletRulesData ($device_name, $outlet_id, $first_rule_id, $count)
	{
		$outlet_rules = self::get_file(self::get_outlet_subdir($device_name), sprintf("%02d", $outlet_id) . self::$controller_file_rules_ext);
		if (!$outlet_rules) {
			return pack("V", 0x00000000);
		}
		return $outlet_rules;
	}

	/**
	 *	@brief
	 *			Get outlet device config (if exists) from registered device. See protocol.h (struct outdev_conf) for more info
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *
	 */
	public function getOutletDeviceData ($device_name)
	{
		$outdev_config = self::get_file(self::get_device_subdir($device_name), self::$controller_file_outlet_device);
		if (!$outdev_config) {
			return pack("V", 0xFFFFFFFF);
		}
		return $outdev_config;
	}

	/**
	 *	@brief
	 *			Get sensor list from the outlet device. Local and remote sensors.
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *
	 *	@return
	 *			struct sensor_broadcast flow that device has heared or status code
	 *
	 */
	public function getOutletSensorlistData ($device_name)
	{
		$outlet_sensorlist = self::get_file(self::get_device_subdir($device_name), self::$controller_file_outlet_sensorlist);
		if (!$outlet_sensorlist) {
			return pack("V", 0xFFFFFFFF);
		}
		return $outlet_sensorlist;
	}

	/**
	 *	@brief
	 *			Count of outlets on the device.
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *
	 */
	public function getOutletCount ($device_name)
	{
		$dir = self::get_outlet_subdir($device_name);

		if (!$dir) {
			return pack("V", 0xFFFFFFFF);
		}

		$outlets = glob($dir . "/*" . self::$controller_file_outlet_config_ext);

		if ($outlets === FALSE) {
			return pack("V", 0xFFFFFFFF);
		}

		$outlet_count = 0;

		if (count($outlets) > 0) {
			$outlet_count = (intval(strtok(basename($outlets[count($outlets)-1]), self::$controller_file_outlet_config_ext)) +1);
		}

		return pack("V", $outlet_count);
	}


	/**
	 *	@brief
	 *			Configuration of the outlets.
	 *
	 *	@param
	 *			$device_name - device identificator (padded MAC)
	 *			$first_outlet_id - outlet order number to begin from.
	 *			$count - count of interested outlets
	 *
	 */
	public function getOutletConfigData ($device_name, $first_outlet_id, $count)
	{
		if ($count <= 0 || (($first_outlet_id + $count) > self::$OUTLET_COUNT_MAX) || ($first_outlet_id < 0)) {
			return pack("V", 0xFFFFFFFF);
		}

		$dir = self::get_outlet_subdir($device_name);

		if (!$dir) {
			return pack("V", 0xFFFFFFFF);
		}


		// append actual state of outlet

		$state_file = $dir . "/" . self::$controller_file_outlet_state;

		$outlet_state_raw = NULL;
		$outlet_state = NULL;
		$os_fd = fopen($state_file, "rb");
		if ($os_fd) {
			if (fseek($os_fd, $first_outlet_id * self::$OUTLET_STATE_SIZE) == 0) {
				$outlet_state_raw = fread($os_fd, $count * self::$OUTLET_STATE_SIZE);
			}
			fclose($os_fd);
		}
		if ($outlet_state_raw) {
			$outlet_state_fmt = "";
			for ($i=0; $i<$count; $i++) {
				$outlet_state_fmt .= (($i > 0)? "/" : "") . "V1state$i/V1chgtime$i";
			}
			$outlet_state = unpack($outlet_state_fmt, $outlet_state_raw);
		}



		$outlet_config = '';
		for ($i=$first_outlet_id; $i<($first_outlet_id + $count); $i++) {
			$conf = self::get_file(self::get_outlet_subdir($device_name), sprintf("%02d", $i) . self::$controller_file_outlet_config_ext);
			if (!$conf) break;
			if ($outlet_state) {
				$outlet_config_unpacked = unpack("v1id/v1consumer/V1state/C128name", $conf);
				if ($outlet_config_unpacked) {
					$outlet_config_unpacked['state'] = $outlet_state["state$i"];
					$conf = pack("vvVC128", ...array_values($outlet_config_unpacked));
				}
			}
			$outlet_config .= $conf;
		}
		if (!$outlet_config) {
			return pack("V", 0xFFFFFFFF);
		}
		return $outlet_config;
	}
}



class Automate
{
	private static $controller_error_code_success				= 0x00000000;
	private static $controller_error_code_no_such_device		= 0xFFFFFFFF;
	private static $controller_error_code_invalid_argument		= 0x00000001;
	private static $controller_error_code_internal_error		= 0x00000002;

	public static $DEVICE_TYPE_UNKNOWN		= 0x00000000;
	public static $DEVICE_TYPE_SENSOR		= 0x00000001;
	public static $DEVICE_TYPE_OUTLET		= 0x00000002;
	public static $DEVICE_TYPE_STEPPER		= 0x00000004;
	public static $DEVICE_TYPE_ENGINE		= 0x00000008;


	private $iData = NULL;

	public function __construct ()
	{
		$this->iData = new FileData();
	}
	public function __destruct ()
	{
	}


	private function response ($data, $len, $error_code)
	{
		header("Content-Type: application/octet-stream", true);
		header("Content-Length: $len", true);
		header("Connection: close", true);

		if ($data && $len > 0) {
			echo $data; return;
		}

		$response = pack("V*", $error_code);
		echo $response;
	}


	private function response_len ($data)
	{
		return (strlen(bin2hex($data))/2);
	}




/**
 *==========================================
 *
 *	user interface:
 *
 *==========================================
 */


	/**
	 *	@brief
	 *			get list of registered device.
	 *
	 *	@param
	 *			query_vars is array of request variables
	 *
	 */
	private function controlGetDeviceList ($query_vars)
	{
		echo "DEVICE LIST. will be here later ...";
	}


	/**
	 *	@brief
	 *			set request for registered device function.
	 *
	 *	@param
	 *			query_vars is array of request variables
	 *
	 */
	private function handleRequest ($query_vars)
	{
		if ((!isset($query_vars['device']) || empty($query_vars['device'])) || (!isset($query_vars['cmd']) || empty($query_vars['cmd']))) {
			// print device list by default
			$this->response(NULL, 4, self::$controller_error_code_invalid_argument);
			return;
		}

		$device_name = htmlspecialchars($query_vars['device']);

		if ($this->iData->isDeviceData($device_name) === FALSE) {
			$this->response(NULL, 4, self::$controller_error_code_no_such_device);
			return;
		}


		if (isset($query_vars['response']) && !empty($query_vars['response'])) {

			$response_id = htmlspecialchars($query_vars['response']);

			$response_data = $this->iData->getResponseData($device_name, $response_id);
			if ($response_data === FALSE) {
				$this->response(NULL, 4, self::$controller_error_code_internal_error);
				return;
			}

			$this->response($response_data, $this->response_len($response_data), self::$controller_error_code_success);
			return;
		}


		// if the request can be satisfied without device apply, then this SHOULD be done

		if ($_SERVER['REQUEST_METHOD'] == 'GET') {

			if ('sensor_getmetric' == $query_vars['cmd']) {
					$result = $this->iData->getSensorMetricData($device_name);
					$this->response($result, $this->response_len($result), self::$controller_error_code_success);
					return;
			}
			else if ('sensor_getconfig' == $query_vars['cmd']) {
					$result = $this->iData->getSensorDeviceData($device_name);
					$this->response($result, $this->response_len($result), self::$controller_error_code_success);
					return;
			}
			else if ('outlet_getdevconf' == $query_vars['cmd']) {
					$result = $this->iData->getOutletDeviceData($device_name);
					$this->response($result, $this->response_len($result), self::$controller_error_code_success);
					return;
			}
			else if ('outlet_getsensorlist' == $query_vars['cmd']) {
					$result = $this->iData->getOutletSensorlistData($device_name);
					$this->response($result, $this->response_len($result), self::$controller_error_code_success);
					return;
			}
			else if ('outlet_getcount' == $query_vars['cmd']) {
					$result = $this->iData->getOutletCount($device_name);
					$this->response($result, $this->response_len($result), self::$controller_error_code_success);
					return;
			}
			else if ('outlet_getconfig' == $query_vars['cmd']) {
					if (
						(!isset($query_vars['oid']) || $query_vars['oid'] == "")
						|| (!isset($query_vars['count']) || $query_vars['count'] == "")
					) {
						$result = pack("V", 0xFFFFFFFF);
						$this->response($result, $this->response_len($result), self::$controller_error_code_success);
						return;
					}

					$first_outlet_id = htmlspecialchars($query_vars['oid']);
					$count = htmlspecialchars($query_vars['count']);

					$result = $this->iData->getOutletConfigData($device_name, intval($first_outlet_id), intval($count));
					$this->response($result, $this->response_len($result), self::$controller_error_code_success);
					return;
			}
			else if ('outlet_getrules' == $query_vars['cmd']) {
					if (
						(!isset($query_vars['oid']) || $query_vars['oid'] == "")
						|| (!isset($query_vars['rid']) || $query_vars['rid'] == "")
						|| (!isset($query_vars['count']) || $query_vars['count'] == "")
					) {
						$result = pack("V", 0xFFFFFFFF);
						$this->response($result, $this->response_len($result), self::$controller_error_code_success);
						return;
					}

					$outlet_id = htmlspecialchars($query_vars['oid']);
					$rule_id = htmlspecialchars($query_vars['rid']);
					$count = htmlspecialchars($query_vars['count']);

					$result = $this->iData->getOutletRulesData($device_name, intval($outlet_id), intval($first_rule_id), intval($count));
					$this->response($result, $this->response_len($result), self::$controller_error_code_success);
					return;
			}
			else if ('system_getdevconf' == $query_vars['cmd']) {
					$result = $this->iData->getDeviceConfigData($device_name);
					$this->response($result, $this->response_len($result), self::$controller_error_code_success);
					return;
			}
		}


		// save request

		$request_data = file_get_contents("php://input");
		$request_id = $this->iData->saveRequestData($device_name, $query_vars, $request_data);

		if ($request_id === FALSE) {
			$this->response(NULL, 4, self::$controller_error_code_internal_error);
			return;
		}

		$request_code = pack("V2", self::$controller_error_code_success, $request_id);
		$this->response($request_code, 8, self::$controller_error_code_success);

		return;
	}



/**
 *==========================================
 *
 *	device interface:
 *
 *==========================================
 */


	/**
	 *	@brief
	 *			set response for user request.
	 *
	 *	@param
	 *			query_vars is array of request variables
	 *
	 */
	private function controlSetResponse ($query_vars)
	{
		if (!isset($query_vars['device']) || empty($query_vars['device']) || !isset($query_vars['response']) || empty($query_vars['response'])) {
			$this->response(NULL, 4, self::$controller_error_code_invalid_argument);
			return;
		}

		$device_name = htmlspecialchars($query_vars['device']);
		$response_id = htmlspecialchars($query_vars['response']);
		$response_data = file_get_contents("php://input");

		if ($this->iData->isDeviceData($device_name) === FALSE) {
			$this->response(NULL, 4, self::$controller_error_code_no_such_device);
			return;
		}


		if ($this->iData->saveResponseData($device_name, $response_id, $response_data) === TRUE) {
			$this->response(NULL, 4, self::$controller_error_code_success);
			return;
		}

		$this->response(NULL, 4, self::$controller_error_code_internal_error);
		return;
	}

	/**
	 *	@brief
	 *			get request for registered device function.
	 *
	 *	@param
	 *			query_vars is array of request variables
	 *
	 */
	private function controlGetRequest ($query_vars)
	{
		if (!isset($query_vars['device']) || empty($query_vars['device'])) {
			$this->response(NULL, 4, self::$controller_error_code_invalid_argument);
			return;
		}

		$device_name = htmlspecialchars($query_vars['device']);

		if ($this->iData->isDeviceData($device_name) === FALSE) {
			$this->response(NULL, 4, self::$controller_error_code_no_such_device);
			return;
		}


		$request_data = $this->iData->getRequestData($device_name);

		if ($request_data === FALSE) {
			$this->response(NULL, 4, self::$controller_error_code_internal_error);
			return;
		}

		$this->response($request_data, $this->response_len($request_data), self::$controller_error_code_success);
		return;
	}


	/**
	 *	@brief
	 *			new device registration function.
	 *
	 *	@param
	 *			query_vars is array of request variables
	 *
	 */
	private function controlAddDevice ($query_vars)
	{
		$new_device_raw = file_get_contents("php://input");


							 	// i_am_here
		$new_device_format = 	"N2"."device_id/" .
								"V1"."device_type/" .
								"V1"."device_state/" .
								"C128"."device_name/" .
								"C16"."device_addr/";

		$new_device = unpack($new_device_format . "C*", $new_device_raw);

		if ($new_device) {

			// write real IP address to device file
			$remote_addr = unpack("C*", inet_pton((isset($_SERVER['REMOTE_ADDR']))? $_SERVER['REMOTE_ADDR'] : "0.0.0.0"));
			if ($remote_addr) {
				for ($n = 16, $i = count($remote_addr); $i > 0; $n--, $i--) {
					$new_device["device_addr$n"] = $remote_addr[$i];
				}
			}
			$device_data = pack("N2V1V1C128C16C*", ...array_values($new_device));

			$device_name = sprintf("%08x", intval($new_device['device_id1'], 16)) .  sprintf("%08x", intval($new_device['device_id2'], 16));

			if ($this->iData->saveDeviceData($new_device['device_type'], $device_name, $device_data) === TRUE) {
				$this->response(NULL, 4, self::$controller_error_code_success);
				return;
			}
		}

		$this->response(NULL, 4, self::$controller_error_code_internal_error);
		return;
	}



	/**
	 *	@brief
	 *			sensor broadcasting data which are visible for the device.
	 *
	 *	@param
	 *			query_vars is array of request variables
	 *
	 */
	private function controlSaveSensorList ($query_vars)
	{
		if (!isset($query_vars['device']) || empty($query_vars['device'])) {
			$this->response(NULL, 4, self::$controller_error_code_invalid_argument);
			return;
		}

		$device_name = htmlspecialchars($query_vars['device']);
		$sensor_broadcast = file_get_contents("php://input");

		if ($this->iData->isDeviceData($device_name) === FALSE) {
			$this->response(NULL, 4, self::$controller_error_code_no_such_device);
			return;
		}


		if ($this->iData->saveOutletSensorlist($device_name, $sensor_broadcast) === TRUE) {
			$this->response(NULL, 4, self::$controller_error_code_success);
			return;
		}

		$this->response(NULL, 4, self::$controller_error_code_internal_error);
		return;
	}



	/**
	 *	@brief
	 *			outlet save rules of outlet.
	 *
	 *	@param
	 *			query_vars is array of request variables
	 *
	 */
	private function controlSaveRules ($query_vars)
	{
		if ((!isset($query_vars['device']) || $query_vars['device'] == "") || (!isset($query_vars['oid']) || $query_vars['oid'] == "")) {
			$this->response(NULL, 4, self::$controller_error_code_invalid_argument);
			return;
		}

		$device_name = htmlspecialchars($query_vars['device']);
		$outlet_id = htmlspecialchars($query_vars['oid']);
		$outlet_rules = file_get_contents("php://input");

		if ($this->iData->isDeviceData($device_name) === FALSE) {
			$this->response(NULL, 4, self::$controller_error_code_no_such_device);
			return;
		}


		if ($this->iData->saveOutletRules($device_name, intval($outlet_id), $outlet_rules) === TRUE) {
			$this->response(NULL, 4, self::$controller_error_code_success);
			return;
		}

		$this->response(NULL, 4, self::$controller_error_code_internal_error);
		return;
	}

	/**
	 *	@brief
	 *			outlet save state of all outlets.
	 *
	 *	@param
	 *			query_vars is array of request variables
	 *
	 */
	private function controlSaveState ($query_vars)
	{
		if (!isset($query_vars['device']) || empty($query_vars['device'])) {
			$this->response(NULL, 4, self::$controller_error_code_invalid_argument);
			return;
		}

		$device_name = htmlspecialchars($query_vars['device']);
		$outlet_state = file_get_contents("php://input");

		if ($this->iData->isDeviceData($device_name) === FALSE) {
			$this->response(NULL, 4, self::$controller_error_code_no_such_device);
			return;
		}

		if ($this->iData->saveOutletStates($device_name, $outlet_state) === TRUE) {
			$this->response(NULL, 4, self::$controller_error_code_success);
			return;
		}

		$this->response(NULL, 4, self::$controller_error_code_internal_error);
		return;
	}

	/**
	 *	@brief
	 *			sensor broadcasting dara receiver function.
	 *
	 *	@param
	 *			query_vars is array of request variables
	 *
	 */
	private function controlSaveMetric ($query_vars)
	{
		$sensor_broadcast_raw = file_get_contents("php://input");
		$sensor_broadcast = unpack(
							"N2"."device_id"
							, $sensor_broadcast_raw
						);

		$device_name = sprintf("%08x", intval($sensor_broadcast['device_id1'], 16)) .  sprintf("%08x", intval($sensor_broadcast['device_id2'], 16));

		if ($sensor_broadcast) {
			if ($this->iData->saveSensorMetricData($device_name, $sensor_broadcast_raw) === TRUE) {
				$this->response(NULL, 4, self::$controller_error_code_success);
				return;
			}
		}

		$this->response(NULL, 4, self::$controller_error_code_internal_error);
		return;
	}





/**
 *==========================================
 *
 *	ENTRY:
 *
 *==========================================
 */


	/**
	 *	@brief
	 *			request entry function.
	 *
	 */
	public function requestEntry ()
	{
		if (!isset($_SERVER['QUERY_STRING']) || empty($_SERVER['QUERY_STRING'])) {
			$this->controlGetDeviceList($query_vars);
	//		$this->response(NULL, 4, self::$controller_error_code_invalid_argument);
			return;
		}

		parse_str($_SERVER['QUERY_STRING'], $query_vars);


		ignore_user_abort(TRUE);
		set_time_limit(0);


		$cmd = ((isset($query_vars['cmd']) && !empty($query_vars['cmd']))? $query_vars['cmd'] : '');

		// search proper function to run

		if ($_SERVER['REQUEST_METHOD'] === 'GET') {

			if ($cmd == 'control_get_request') {
				$this->controlGetRequest($query_vars);
			}
			else if ($cmd == 'control_get_devicelist') {
				$this->controlGetDeviceList($query_vars);
			}
			else {
				$this->handleRequest($query_vars);
			}

			return;
		}
		else if ($_SERVER['REQUEST_METHOD'] === 'POST') {

			if ($cmd == 'control_save_metric') {
				$this->controlSaveMetric($query_vars);
			}
			else if ($cmd == 'control_save_sensorlist') {
				$this->controlSaveSensorList($query_vars);
			}
			else if ($cmd == 'control_save_rules') {
				$this->controlSaveRules($query_vars);
			}
			else if ($cmd == 'control_save_state') {
				$this->controlSaveState($query_vars);
			}
			else if ($cmd == 'control_set_response') {
				$this->controlSetResponse($query_vars);
			}
			else if ($cmd == 'control_add_device') {
				$this->controlAddDevice($query_vars);
			}
			else {
				$this->handleRequest($query_vars);
			}

			return;
		}

		$this->response(NULL, 4, self::$controller_error_code_invalid_argument);
		return;
	}
}



?>
