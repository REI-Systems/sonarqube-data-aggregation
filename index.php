<?php
header("Content-type:application/json");
/*---------------- Use Buzz HTTPClient -------------------*/
require_once 'vendor/autoload.php';
//Load the PHPExcel library to produce output in Excel format
require_once "lib/PHPExcel/Classes/PHPExcel.php";

/*--------------------------------------------------------*/
// ------------- Connecting with Username and Password -------------- //
//$client = new \SonarQube\Client('hostname', 'username', 'password');

// ------------- Connecting with tokens -------------- //
//$client = new \SonarQube\Client('hostname', 'token', '');

$cache_file = dirname(__FILE__) . '/api-cache.array';
$json_file = dirname(__FILE__) . '/api-cache.json';
$settings_file = dirname(__FILE__) . '/settings.array';
$cred_file = dirname(__FILE__) . '/settings.json';
$purgeCache = false;
$settings = unserialize(file_get_contents($settings_file));
$cred = json_decode(file_get_contents($cred_file));
$uriA = $cred->uriA;
$uriB = $cred->uriB;
$uriC = $cred->uriC;
$tokenA = $cred->tokenA;
$tokenB = $cred->tokenB;
$tokenC = $cred->tokenC;

if(empty($settings)){
  $expires = time() + 24*60*60;
  $setting = array();
  $setting['expires'] = $expires;
  file_put_contents($settings_file, serialize($setting));
}else{
  $expires = $settings['expires'];
}
if ( time() > $expires || empty(unserialize(file_get_contents($cache_file))) || $purgeCache) {
  $arrayQuery = array();
  $arrayA = queryMetrics($uriA, $tokenA);
  $arrayB = queryMetrics($uriB, $tokenB);
  $arrayC = queryMetrics($uriC, $tokenC);
  $arrayQuery[] = $arrayA;
  $arrayQuery[] = $arrayB;
  $arrayQuery[] = $arrayC;
  
  if ( $arrayQuery ){
    //Call the method to transform array to Excel format
    JSONArraytoExcel($arrayQuery);
    file_put_contents($cache_file, serialize($arrayQuery));
    file_put_contents($json_file, json_encode($arrayQuery));
  }else{
    unlink($cache_file);
  }
  $expires = time() + 24*60*60;
  $setting = array();
  $setting['expires'] = $expires;
  file_put_contents($settings_file, serialize($setting));
} else {
  //Convert the json string to array
  $json_to_array = unserialize(file_get_contents($cache_file));
  //Call the method to transform array to Excel format
  JSONArraytoExcel($json_to_array);
}

function queryMetrics($hostname, $token){
  $client = new \SonarQube\Client($hostname, $token, '');
  $authentication = $client->api('authentication')->validate();
  $projects = $client->projects->search();
  $array = array();
  $arrayExcel = array();
  foreach ($projects as $project)
  {
      $arrayParent = array();
      $arrayChild = array();
      $arrayParent['project'] = $project["k"];
      $measures = $client->measures->component(['componentKey'=>$project["k"],'additionalFields'=>'periods','metricKeys'=>'bugs,reliability_rating,new_reliability_rating,vulnerabilities,security_rating,new_security_rating,code_smells,sqale_rating,new_maintainability_rating,coverage,new_bugs,new_coverage,new_code_smells,new_vulnerabilities']);
      $measuresVal = $measures['component']['measures'];
      $periodsVal = $measures['periods'];
      foreach ($measuresVal as $measure){
        if($measure['metric']=="reliability_rating" || $measure['metric']=="security_rating" || $measure['metric']=="sqale_rating"){
          $arrayChild[$measure['metric']] = ratingChecker($measure['value']);
        }else if($measure['metric']=="new_coverage" || $measure['metric']=="new_bugs" || $measure['metric']=="new_vulnerabilities" || $measure['metric']=="new_code_smells"){
          foreach($measure['periods'] as $newMeasure){
            $arrayChild[$measure['metric']] = $newMeasure['value'];
          }
        }else if($measure['metric']=="new_reliability_rating" || $measure['metric']=="new_security_rating" || $measure['metric']=="new_maintainability_rating"){
            $arrayChild[$measure['metric']] = ratingChecker($measure['periods'][0]['value']);
        }else{
            $arrayChild[$measure['metric']] = $measure['value'];
        }
      }
      foreach ($periodsVal as $period){
          $arrayChild['last_analyzed'] = $period['date'];
      }
      $arrayParent['metrics'] = $arrayChild;
      //Call the split project method to split into multiple and add new elements 
      $arrayParentCopy = splitProject_id($arrayParent, $hostname);
      //Add the returned multiple array to the arrayExcel(the big array)
      foreach($arrayParentCopy as $arrayParent_single){
        $arrayExcel[] = $arrayParent_single;
      }
      $array[] = $arrayParent;
  }
    return $arrayExcel;
}

function ratingChecker($rscale){
  if($rscale==1.0){
    return "A";
  }else if($rscale==2.0){
    return "B";
  }else if($rscale==3.0){
    return "C";
  }else if($rscale==4.0){
    return "D";
  }else if($rscale==5.0){
    return "E";
  }else{
    return "F";
  }
}

function splitProject_id($arrayParent, $projectURL){
$arrayCopy = array();
if (!empty($arrayParent)){
  //Explode the Project_id with dot(.)
  $array_psr = explode('.',  $arrayParent['project']);
  $numeric_matches = array();
  //Loop thru individual item in array_psr
  foreach($array_psr as $psr){
    //Check if numeric, if yes then add it to the $numeric_matches variable
    if (is_numeric($psr)){
      $numeric_matches[] = $psr;
    }
  }
  //If numeric_matches is 0, some projects do not have number in them, call separate loop
  if (count($numeric_matches) == 0){
    //Get the code base name (the end part of the project id)
    $code_base_name = $array_psr[count($array_psr) - 1];
    $numeric_matches[] = null;
  }
  else{
    //Get the last value of the numeric_matches
    $total_psr = end($numeric_matches);
    //Get the code base name with str match and sub string
    $code_base_name = substr(strstr($arrayParent['project'], $total_psr), strlen($total_psr) + 1);
  }
  //Remove the "api" part of the Project URL
  $base_url = strstr($projectURL, 'api', true);
  //Get the collection date (UTC)
  $collection_date = date('Y-m-d\TH:i:sZ');
  //Create new array for individual psr in the numeric_matches
  foreach($numeric_matches as $psr){
    //Add the code base name to the array
    $arrayParent = array('code_base_name' => $code_base_name)+ $arrayParent;
    //Add the URL to the array
    $arrayParent = array('URL' => $base_url) + $arrayParent;
    //Add the psr_id to the array
    $arrayParent = array('psr_id' => $psr) + $arrayParent;
    //Add the collection_time to the array
    $arrayParent = array('collection_time' => $collection_date) + $arrayParent;
    //Add the array to the main array(arrayCopy)
    $arrayCopy[] = $arrayParent;
  }
}
return $arrayCopy;
}

function JSONArraytoExcel($dataArray){
  //Define the excel object
  $doc = new PHPExcel();
  //Set the first sheet
  $doc->setActiveSheetIndex(0);
  //Define the column indexes in array
  $column_index = array(
    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K',
    'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
    'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK',
    'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT'
  );
  //Define the header style of the Excel sheet
  $header_style = array(
    'font'  => array(
      'bold'  => true,
      'color' => array('argb' => PHPExcel_Style_Color::COLOR_BLACK),
      'size'  => 14,
    ),
    'fill' => array(
      'type' => PHPExcel_Style_Fill::FILL_SOLID,
      'color' => array('argb' => PHPExcel_Style_Color::COLOR_YELLOW)
    )
  );
  //Define the row style - not being used as of now
  $row_style_grey = array(
    'fill' => array(
      'type' => PHPExcel_Style_Fill::FILL_SOLID,
      'color' => array('argb' => 'D0D0D0')
    )
  );
  //Select the first row as header
  $row = 1;
  //Define the header columns 
  $header_array = array(
    'collection_date',
    'psr_id',
    'url',
    'sonarqube_project_id',
    'code_base_name',
    'metrics_coverage',
    'metrics__new_vulnerabilities_rating',
    'metrics__new_vulnerabilities',
    'metrics__new_code_smells',
    'metrics__vulnerabilities',
    'metrics__vulnerabilities_rating',
    'metrics__new_bugs_rating',
    'metrics__code_smells',
    'metrics__new_coverage',
    'metrics_new_bugs',
    'metrics__new_code_smells_rating',
    'metrics__bugs',
    'metrics__sqale_rating',
    'metrics__code_smells_rating',
    'metrics__last_analyzed'
  );
  //Start with column 0 (first column), select individual cell and use the header value from the array above
  $column = 0;
  foreach($header_array as $header){
    $doc->getActiveSheet()->setCellValue("{$column_index[$column]}{$row}", $header);
    //Add the style to the header
    $doc->getActiveSheet()->getStyle("{$column_index[$column]}{$row}")->applyFromArray($header_style);
    $column++;
  }
  //Increment the row to 1 (second row) to write all the values
  $row++;
  
  foreach($dataArray as $big_data){
    foreach($big_data as $data){
      //Set individual cell with value, also to set the cell, the cell is has to be absolute
      //Collection Time
      $doc->getActiveSheet()->setCellValue($column_index[0].(string)$row, $data['collection_time']);
      //PSR ID
      $doc->getActiveSheet()->setCellValue($column_index[1].(string)$row, $data['psr_id']);
      //URL
      $doc->getActiveSheet()->setCellValue($column_index[2].(string)$row, $data['URL']);
      //Project ID
      $doc->getActiveSheet()->setCellValue($column_index[3].(string)$row, $data['project']);
      //Code Base Name
      $doc->getActiveSheet()->setCellValue($column_index[4].(string)$row, $data['code_base_name']);
      //Coverage
      if (isset($data['metrics']['coverage'])){
        $doc->getActiveSheet()->setCellValue($column_index[5].(string)$row, $data['metrics']['coverage']);
      }
      //new_vulnerabilities_rating      
      if (isset($data['metrics']['new_security_rating'])){
        $doc->getActiveSheet()->setCellValue($column_index[6].(string)$row, $data['metrics']['new_security_rating']);
      }
      //New Vulnerabilities
      if (isset($data['metrics']['new_vulnerabilities'])){
        $doc->getActiveSheet()->setCellValue($column_index[7].(string)$row, $data['metrics']['new_vulnerabilities']);
      }
      //New Code Smells
      if (isset($data['metrics']['new_code_smells'])){
        $doc->getActiveSheet()->setCellValue($column_index[8].(string)$row, $data['metrics']['new_code_smells']);
      }
      //Vulnerabilities
      if (isset($data['metrics']['vulnerabilities'])){
        $doc->getActiveSheet()->setCellValue($column_index[9].(string)$row, $data['metrics']['vulnerabilities']);
      }
      //vulnerabilities_rating
      if (isset($data['metrics']['security_rating'])){
        $doc->getActiveSheet()->setCellValue($column_index[10].(string)$row, $data['metrics']['security_rating']);
      }
      //new_bugs_rating
      if (isset($data['metrics']['new_reliability_rating'])){
        $doc->getActiveSheet()->setCellValue($column_index[11].(string)$row, $data['metrics']['new_reliability_rating']);
      }
      //Code Smells
      if (isset($data['metrics']['code_smells'])){
        $doc->getActiveSheet()->setCellValue($column_index[12].(string)$row, $data['metrics']['code_smells']);
      }
      //New Coverage
      if (isset($data['metrics']['new_coverage'])){
        $doc->getActiveSheet()->setCellValue($column_index[13].(string)$row, $data['metrics']['new_coverage']);
      }
      //New Bugs
      if (isset($data['metrics']['new_bugs'])){
        $doc->getActiveSheet()->setCellValue($column_index[14].(string)$row, $data['metrics']['new_bugs']);
      }
      //new_code_smells_rating          
      if (isset($data['metrics']['new_maintainability_rating'])){
        $doc->getActiveSheet()->setCellValue($column_index[15].(string)$row, $data['metrics']['new_maintainability_rating']);
      }
      //Bugs
      if (isset($data['metrics']['bugs'])){
        $doc->getActiveSheet()->setCellValue($column_index[16].(string)$row, $data['metrics']['bugs']);
      }
      //Squale Rating
      if (isset($data['metrics']['sqale_rating'])){
        $doc->getActiveSheet()->setCellValue($column_index[17].(string)$row, $data['metrics']['sqale_rating']);
      }
      //code_smells_rating                     
      if (isset($data['metrics']['reliability_rating'])){
        $doc->getActiveSheet()->setCellValue($column_index[18].(string)$row, $data['metrics']['reliability_rating']);
      }
      //Last Analyzed
      if (isset($data['metrics']['last_analyzed'])){
        $doc->getActiveSheet()->setCellValue($column_index[19].(string)$row, $data['metrics']['last_analyzed']);
      }
      //Increment the row
      $row++;
    }

  }
  //Following code is needed to set the cell to Autosize (Only works with Excel)
  /*foreach ($doc->getWorksheetIterator() as $worksheet) {
    $doc->setActiveSheetIndex($doc->getIndex($worksheet));
    $sheet = $doc->getActiveSheet();
    $cellIterator = $sheet->getRowIterator()->current()->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(true);
    // @var PHPExcel_Cell $cell
    foreach ($cellIterator as $cell) {
        $sheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
    }
  }*/
  //Get the Current Date time of EST zone
  $date = new DateTime('now', new DateTimeZone('America/New_York'));
  //Calculate the file name as sonar_results appended with Date and Time
  $filename = 'SonarQube_results_' . $date->format('F_m_d_H_i') .'.csv';
  //header('Content-Type: application/vnd.ms-excel');
  //CSV content type
  header('Content-Type:text/csv');
  header('Content-Disposition: attachment;filename="' . $filename . '"');
  header('Cache-Control: max-age=0');
  //Write to the Excel
  //$objWriter = PHPExcel_IOFactory::createWriter($doc, 'Excel5');
  //Write to CSV
  $objWriter = PHPExcel_IOFactory::createWriter($doc, 'CSV');
  //Produce the output
  $objWriter->save('php://output');
  exit;
}
?>
