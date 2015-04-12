<?php

	include __DIR__ . '/../entity/TokenBlock.php';
	include __DIR__ . '/../entity/Enviroment.php';
	include __DIR__ . '/../parser/ArgParser.php';
	include __DIR__ . '/../metrics/halstead/Halstead.php';
	include __DIR__ . '/../workers/TokensWorker.php';
	include __DIR__ . '/../workers/DirectoryWorker.php';
	include __DIR__ . '/../utils/JsonUtils.php';
	include __DIR__ . '/../utils/ArrayUtils.php';
	include __DIR__ . '/../utils/Logger.php';	

	// phases
	// phase 1 - DONE
	// phase 2 - ze zadaneho json project & template souboru vygenerovat dvojice do csv souboru
	// phase 3 - eval halstead + eval levensthein
	// phase 4 - eval winnowing
	
	// ============= main workflow =============
	$arguments = getArguments($argc, $argv);
	if (is_null($arguments)) 
		exit();
		
	// print help if needed
	if ($arguments->getIsHelp()) {
		ArgParser::printHelp();
		exit();
	}
	
	$enviroment = new Enviroment();
	
	// process phases
	if ($arguments->getIsGlobalFlow() || $arguments->getIsGenerateFiles() || $arguments->getIsStepOne()) {
		$enviroment = processFirstPhase($arguments);
		if (is_null($enviroment))
			exit();
	}
	
	if ($arguments->getIsGlobalFlow() || $arguments->getIsGenerateFiles() || $arguments->getIsStepTwo()) {
		$enviroment = processSecondPhase($arguments, $enviroment);
		if (is_null($enviroment))
			exit();
	}
		

	// ======== controller functions =========
	
	/**
	 * 
	 * Loads arguments into argument entity. Returns null if exception occurred.
	 * @param $argc
	 * @param $argv
	 */
	function getArguments($argc, $argv) {
		$argParser = new ArgParser($argc, $argv);
		try {
			$arguments = $argParser->parseArguments();
		}
		catch (InvalidArgumentException $iae) {
			return null;
		}
		
		return $arguments;
	} 
	
	/**
	 * Generates JSON file from assignments.
	 * @return Enviroment entity containing JSON file with projects and templates. 
	 */
	function processFirstPhase($arguments) {
		
		$enviroment = new Enviroment();
		
		// set template JSON file if delivered
		if (!is_null($arguments->getTemplateJSON())) {
			try {
				$enviroment->setTemplate(JsonUtils::getJsonFromFile($arguments->getTemplateJSON()));
			}
			catch (Exception $ex) {
				Logger::errorFatal('Error during loading template JSON file. ');
				return null;
			}
		} // TODO toto by se snad dalo vyskrtnout ne?
		
		// set input JSON file if delivered, otherwise creates it from input path
		if (!is_null($arguments->getInputJSON())) {
			try {
				$enviroment->setProject(JsonUtils::getJsonFromFile($arguments->getInputJSON()));
			}
			catch (Exception $ex) {
				Logger::errorFatal('Error during loading input JSON file. ');
				return null;
			}
		}
		else {
			$project = DirectoryWorker::getSubDirectories($arguments->getInputPath(), $arguments->getIsRemoveComments());
			$enviroment->setProject($project);
			
			// save json
			try {
				JsonUtils::saveToJson($arguments->getOutputPath(), $arguments->getJsonOutputFilename() . Constant::JSON_FILE_EXTENSION,
						$enviroment->getProject());
				Logger::info('JSON file with assignments was successfuly created. ');
			}
			catch (Exception $ex) {
				Logger::error('Error during saving JSON file with assignments. ');
			}
		}
		
		return $enviroment;
	}
	
	/**
	 * Generated unique pairs of assignments.
	 * @return Enviroment entity containing matched pairs and JSON objects.
	 */
	function processSecondPhase($arguments, $enviroment) {
		
		// load input JSON file if first phase was not done
		if (is_null($enviroment->getProject())) {
			if (is_null($arguments->getInputJSON())) { // should not happen
				Logger::errorFatal('No input files were delivered. ');
				return null;
			}
			
			try {
				$enviroment->setProject(JsonUtils::getJsonFromFile($arguments->getInputJSON()));
				Logger::info('JSON file with assignments was successfuly loaded. ');
			}
			catch (Exception $ex) {
				Logger::errorFatal('Error during loading input JSON file. ');
				return null;
			}
		}
		
		// load template JSON file if first phase was not done
		if (is_null($enviroment->getTemplate()) && !is_null($arguments->getTemplateJSON())) {
			try {
				$enviroment->setTemplate(JsonUtils::getJsonFromFile($arguments->getTemplateJSON()));
				Logger::info('JSON file with templates was successfuly loaded. ');
			}
			catch (Exception $ex) {
				Logger::errorFatal('Error during loading template JSON file. ');
				return null;
			} // TODO ukladat log do souboru ?? + pridat k nemu timestamp
		}
		
		$matchedPairs = ArrayUtils::getUniquePairs($enviroment->getProject(), $enviroment->getTemplate());
		$enviroment->setMatchedPairs($matchedPairs);
		
		// export csv
		try {
			JsonUtils::saveToCSV($arguments->getOutputPath(), $arguments->getCsvOutputFilename(), $matchedPairs);
			Logger::info('CSV file with unique pairs was successfuly created. ');
		}
		catch (Exception $ex) {
			Logger::error('Error during saving CSV file. ');
		}
		// TODO refaktorovat vsechny JSON a CSV nazvy na velke
		return $enviroment;
	}
	
?>
