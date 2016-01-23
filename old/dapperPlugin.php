<?php

/**
 * All the standard functions for a Dapper implementation
 */
class DapperPlugin {
	function getName(){
		return $pluginName;
	}

	function activate(){
		$dapper = DapperMapper::getInstance();
		$dapper->addPlugin($this);
	}

	function load_scripts(){
		return Array();
	}

	abstract function saveSettings();
	abstract function getSettings();

}
