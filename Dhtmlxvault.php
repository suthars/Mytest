<?php
class Dhtmlxvault extends CWidget{     
	public $fileLimit = 1;
	public $allowedFileTypes = array();
	public $containerDivID = 'vaultObj';	
    public function  __construct($owner=null) {
        parent::__construct($owner);
         $this->publishAssets();
       
    }
    public function publishAssets() {
        $assets = dirname(__FILE__) . '/assets';
        $baseUrl = Yii::app()->assetManager->publish($assets);
        if (is_dir($assets)) {			
			Yii::app()->clientScript->registerScriptFile($baseUrl.'/codebase/dhtmlxvault.js',CClientScript::POS_END);
			Yii::app()->clientScript->registerScriptFile($baseUrl.'/codebase/swfobject.js',CClientScript::POS_END);
        	Yii::app()->clientScript->registerCssFile($baseUrl.'/web/dhtmlxvault.css');
        } else {
            throw new Exception('DhtmlxVault - Error: Couldn\'t find assets to publish.');
        }
    }

    public function run(){
	
        parent::run();
		$divId = $this->containerDivID;
		//echo "testing: ".$this->containerDivID;exit;
  		$script = "";
		$configurl=Yii::app()->createurl('/site/vault.config');
		$uploadurl=Yii::app()->createurl('/site/vault.upload');
		$script.= "var myVault;
				$(document).ready(function(){
					window.dhx4.ajax.post('".$configurl."','CSRF_SYSTEM_TOKEN='+csrftokenvalue+'&divId=".$divId."', function(r){
						var t = null;
						try {eval('t='+r.xmlDoc.responseText);}catch(e) {} ;
						if (t != null) {
							myVault = new dhtmlXVaultObject(t);
							setSkin('dhx_web');";
							if($this->fileLimit!=0){
								$script.= "myVault.setFilesLimit(".$this->fileLimit.");";
							}
							if(!empty($this->allowedFileTypes)){
								$str = "";
								foreach($this->allowedFileTypes as $key => $value){
									if($str == ""){
										$str .= "ext=='".$value."'";
									}else{
										$str .= " || ext=='".$value."'";
									}
								}
								$script.= "myVault.attachEvent('onBeforeFileAdd', function(file){
									var ext = this.getFileExtension(file.name);
									return (".$str.");
								});";
							}
							$script.= "
							myVault.attachEvent('onUploadFile', function(file, extra){
								//console.log(file.name);
								$('#dhtmlxVault').val(JSON.stringify(myVault.getData()));
							});
							myVault.attachEvent('onFileRemove', function(file){
								if (typeof imageSize === 'undefined') {
									imageSize = '';
								}
								$.ajaxQueue({
									type: 'POST',
									url: '".$uploadurl."',
									data: { Delete: 'yes', fileName: file.serverName , CSRF_SYSTEM_TOKEN : csrftokenvalue,imageSize: imageSize },
									success: function(response){ 
										//console.log(response.extra.info);
										$('#dhtmlxVault').val(JSON.stringify(myVault.getData()));
									}
								});
							});
						}
					});
					
				});
				function setSkin(skin) {
					if (myVault != null) myVault.setSkin(skin);
				}";	
  
		Yii::app()->clientScript->registerScript('dhtmlxvault',$script,CClientScript::POS_LOAD);
		echo '<div id="'.$this->containerDivID.'" style="width:100%; height:210px;"></div>';
	}
	public static function actions() {
		if(isset($_REQUEST['divId'])){
    		return array("config"=>array('class'=>'GetConfig','divID'=>$_REQUEST['divId']),"upload" => "UploadFile");
		}else{
			return array("config"=>array('class'=>'GetConfig'),"upload" => "UploadFile");
		}
  	}
	
}
class GetConfig extends CAction {
	public $divID;
	
  public function run() {
	$url=Yii::app()->createurl('/site/vault.upload');
  	//$url = "/site/vault.upload";	
	print_r(json_encode(array(
		"parent"	=> $this->divID,		// container for init, common for all demos
		"uploadUrl"	=> $url,		// html4/html5 upload url
		"swfUrl"	=> $url,		// flash upload url
		"slUrl"		=> $url,		// silverlight upload url, FULL path required
		"swfPath"	=> "dhxvault.swf",	// path to flash uploader
		"slXap"		=> "dhxvault.xap"	// path to silverlight uploader
	)));
  }

}

class UploadFile extends CAction {
  public function run() {
  		
		$size='100x100,200x175';
		$size_array = array();
		if(isset($_POST['imageSize']) && $_POST['imageSize']!=''){
			$size = $_POST['imageSize'].','.$size;
		}
		$size_array = explode(',',$size);
		if(@$_POST["Delete"] == "yes"){			
			$filename = $_POST["fileName"];
			$extarray = array_reverse(explode('.', $filename));
			$origFileName = str_replace("%s","",$filename);
			if(strtolower($extarray[0]) != "mp4" && strtolower($extarray[0]) != "pdf" && strtolower($extarray[0]) != "ppt" && strtolower($extarray[0]) != "pptx" && strtolower($extarray[0]) != "doc" && strtolower($extarray[0]) != "docx" && strtolower($extarray[0]) != "xls" && strtolower($extarray[0]) != "xlsx")
			{
				
				foreach($size_array as $key => $value){
					$thumb_name = str_replace("%s","_".$value,$filename);
					unlink(YiiBase::getPathOfAlias("webroot")."/".$thumb_name);
				}
				
			}
			unlink(YiiBase::getPathOfAlias("webroot")."/".$origFileName);
			
			
			//header("Content-Type: text/json");
			print_r(json_encode(array(
				"state" => true,
				"extra" => array(
					"info"  => "File deleted successfully",
				)
			)));
			die();
		}
		
		
		if (@$_REQUEST["mode"] == "conf") {
			function parse_num($k) {
				$p = 0;
				preg_match("/(\d{1,})([kmg]?)/i", trim($k), $r);
				if (isset($r) && isset($r[1])) {
					$p = $r[1];
					if (isset($r[2])) {
						switch(strtolower($r[2])) {
							case "g": $p *= 1024;
							case "m": $p *= 1024;
							case "k": $p *= 1024;
						}
					}
				}
				return $p;
			}
			
			//header("Content-Type: text/json");
			print_r(json_encode(array(
				"maxFileSize" => min(parse_num(ini_get("upload_max_filesize")), parse_num(ini_get("post_max_size")))
			)));
			
			die();
		}
		
		/*
		
		HTML5/FLASH MODE
		
		(MODE will detected on client side automaticaly. Working mode will passed to server as GET param "mode")
		
		response format
		
		if upload was good, you need to specify state=true and name - will passed in form.send() as serverName param
		{state: 'true', name: 'filename'}
		
		*/
		
		if (@$_REQUEST["mode"] == "html5" || @$_REQUEST["mode"] == "flash") {
			$size = array();
			if (@$_REQUEST["zero_size"] == "1") {
				$filename = @$_REQUEST["file_name"];
				file_put_contents(YiiBase::getPathOfAlias("webroot")."/uploaded/".$filename, ""); // IE10,IE11 zero file fix
			} else {
				$modulename = "";
				if(isset($_POST['currentModuleName'])){
					$modulename = $_POST['currentModuleName'];
				}
				
				$size = $size_array;	
				$logo = CUploadedFile::getInstanceByName("file");
				 if ($logo) {
					$storimage = uniqid(true);
					$extarray = array_reverse(explode('.', $logo->name));
					$name_str = $storimage . '%s.' . $extarray[0];
					$name = $storimage . '.' . $extarray[0];
					$imageuploadobj = new imageUploadHolder($modulename, $name);
					$newp = $imageuploadobj->imageupload();                               
					$imageuploadobj->saveimage($logo);
					
		
					if(strtolower($extarray[0]) != "mp4" && strtolower($extarray[0]) != "pdf" && strtolower($extarray[0]) != "ppt" && strtolower($extarray[0]) != "pptx" && strtolower($extarray[0]) != "doc" && strtolower($extarray[0]) != "docx" && strtolower($extarray[0]) != "xls" && strtolower($extarray[0]) != "xlsx")
					{
						foreach($size as $key=>$value){
							$sizearray = explode('x', $value);
							$imageuploadobj->savethumbnail($sizearray[0], $sizearray[1]);
							//sleep(1);
						}
					}
					$filename = $newp . '/' . $name_str;
				 }
			}
			print_r(json_encode(array(
				"state" => true,
				"name"  => $filename,
				"extra" => ""
			)));
			die();
			
		}
		
		/*
		
		HTML4 MODE
		
		response format:
		
		to cancel uploading
		{state: 'cancelled'}
		
		if upload was good, you need to specify state=true, name - will passed in form.send() as serverName param, size - filesize to update in list
		{state: 'true', name: 'filename', size: 1234}
		
		*/
		
		if (@$_REQUEST["mode"] == "html4") {
			//header("Content-Type: text/html");
			if (@$_REQUEST["action"] == "cancel") {
				print_r(json_encode(array("state"=>"cancelled")));
			} else {
				$modulename = "";
				$Object = "";
				 $logo = CUploadedFile::getInstance($Object,'logo_name');
				 
				 if ($logo) {
					$storimage = time();
					$extarray = array_reverse(explode('.', $logo->name));
					$name_str = $storimage . '%s.' . $extarray[0];
					$name = $storimage . '.' . $extarray[0];
					$imageuploadobj = new imageUploadHolder($modulename, $name);
					$newp = $imageuploadobj->imageupload();                               
					$imageuploadobj->saveimage($logo);
					$check = array('0' => 'tiff', '1' => 'gif', '2' => 'jpg', '3' => 'jpeg', '4' => 'bmp', '5' => 'png');
					$size = $size_array;
		
					
					if (in_array(strtolower($extarray[0]), $check)) {
						foreach($size as $key=>$value){
							$sizearray = explode('x', $value);
							$imageuploadobj->savethumbnail($sizearray[0], $sizearray[1]);
						}	
					}
					$finalFileName = $newp . '/' . $name_str;
				 }
			
			
				$filename = $_FILES["file"]["name"];
				move_uploaded_file($_FILES["file"]["tmp_name"], YiiBase::getPathOfAlias("webroot")."/uploaded/".$filename);
				print_r(json_encode(array(
				"state" => true,
				"name"  => $filename,
				"extra" => ""
			)));
			}
			die();
		}
		
		/* SILVERLIGHT MODE */
		/*
		{state: true, name: 'filename', size: 1234}
		*/
		
		if (@$_REQUEST["mode"] == "sl" && isset($_REQUEST["fileSize"]) && isset($_REQUEST["fileName"]) && isset($_REQUEST["fileKey"])) {
			
			// available params
			// $_REQUEST["fileName"], $_REQUEST["fileSize"], $_REQUEST["fileKey"] are available here
			
			// each file got temporary 12-chars length key
			// due some inner silverlight limitations, there will another request to check if file transferred and saved corrrectly
			// key matched to regex below
			
			preg_match("/^[a-z0-9]{12}$/", $_REQUEST["fileKey"], $p);
			
			if (@$p[0] === $_REQUEST["fileKey"]) {
				
				// generate temp name for saving
				$temp_name = YiiBase::getPathOfAlias("webroot")."/uploaded/".md5($p[0]);
				
				// if action=="getUploadStatus" - that means file transfer was done and silverlight is wondering if php/orhet_server_side
				// got and saved file correctly or not, filekey same for both requests
				
				if (@$_REQUEST["action"] != "getUploadStatus") {
					// file is coming, save under temp name
					/*
					$postData = file_get_contents("php://input");
					if (strlen($postData) == $_REQUEST["fileSize"]) {
						file_put_contents($temp_name, $postData);
					}
					*/
					
					// no needs to output something
				} else {
					// second "check" request is coming
					/*
					$state = "false";
					if (file_exists($temp_name)) {
						rename($temp_name, "uploaded/".$_REQUEST["fileName"]);
						$state = "true";
					}
					*/
					
					$state = "true"; // just for tests
					
					// print upload state
					// state: true/false (w/o any quotes)
					// name: server name/id
					//header("Content-Type: text/json");
					print_r('{state: '.$state.', name: "'.str_replace('"','\\"',$_REQUEST["fileName"]).'",extra:{info:"uploaded successfully",param:"some value"}}');
				}
			}
		}
		
		
		/*
		
		CUSTOM FILE RECORD, added in 2.3
		
		response: {state: true, name: 'filename', size: 1234}
		
		state	true/false
		name	server file name
		size	optional, will update size in list of specified
		
		*/
		
		if (@$_REQUEST["mode"] == "custom") {
			sleep(1);
			echo "{state: true, name: '".str_replace("'", "\\'", $_REQUEST["name"])."', extra: {param: 'value'}}";
		}

  }
}
  

?>
