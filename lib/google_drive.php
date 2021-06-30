<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file wraps the Google Drive API
	 *
	 * Required Composer packages:
	 *    google/apiclient
	 */

	# Create Google Drive web service object that wraps Google Drive-specific HTTP REST calls
	function google_drive_service() {
		static $service;
		if ($service === null) {
			$client = google_api_client();

			# Set user explicitly. Not sure which user would be used anyway, if you don't actually explicitly specify the user
			$client->setSubject(get_system_cache()->read('google_login_email'));

			$service = new Google_Service_Drive($client);
		}
		return $service;
	}

	# Upload file to Google Drive.
	# If !!$folderName then the file is uploaded to $baseParentFolder/$folderName/$fileName
	# If !$folderName then the file is uploaded directly to $baseParentFolder/$fileName only
	function google_drive_upload_file($baseParentFolderID, $folderName, $fileName, $data, $mime) {
		try {
			if ($folderName) {
				# Find folder ID
				log_error('Google Drive upload_file', $folderName, $fileName, $mime);
				log_error('Google Drive upload_file trying to get folder ID');
				$response = google_drive_service()->files->listFiles([
					'q'	=> sprintf("'$baseParentFolderID' in parents and name='$folderName' and mimeType='application/vnd.google-apps.folder'"),
					'spaces' => 'drive',
					'pageToken' => null,
					'fields' => 'files(id)',
				]);
				$folderID = isset($response->files[0]) ? $response->files[0]->id : null;
				log_error('Google Drive upload_file got folder ID [1]', $folderID);

				# Create folder if not exists
				if (!$folderID) {
					$fileMetadata = new Google_Service_Drive_DriveFile([
						'name' => $folderName,
						'mimeType' => 'application/vnd.google-apps.folder',
						'parents' => [$baseParentFolderID]
					]);
					$file = google_drive_service()->files->create($fileMetadata, [
						'fields' => 'id'
					]);
					$folderID = $file->id;
					log_error('Google Drive upload_file got folder ID [2]', $folderID);
				}
			} else {
				$folderID = $baseParentFolderID;
			}

			# Upload file
			$fileMetadata = new Google_Service_Drive_DriveFile([
				'name' => $fileName,
				'parents' => [$folderID]
			]);
			$file = google_drive_service()->files->create($fileMetadata, [
				'data' => $data,
				'mimeType' => $mime,
				'uploadType' => 'multipart',
				'fields' => 'id'
			]);
			log_error('Google Drive upload_file uploaded file ID', $file->id);
			return $file->id;
		} catch (Exception $e) {
			log_error('Google Drive upload_file error', $e->getMessage());
		}
		return null;
	}

	# Upload file to Google Drive
	function google_drive_delete_file($baseParentFolderID, $folderName, $fileID) {
		try {
			# Find folder ID
			$response = google_drive_service()->files->listFiles([
				'q'	=> sprintf("'%s' in parents and name='$folderName' and mimeType='application/vnd.google-apps.folder'", $baseParentFolderID),
				'spaces' => 'drive',
				'pageToken' => null,
				'fields' => 'files(id)',
			]);
			$folderID = isset($response->files[0]) ? $response->files[0]->id : null;
			log_error('Google Drive delete_file got folder ID', $folderID);

			# Delete file if folder exists
			if (!$folderID) {
				# Find file ID
				$response = google_drive_service()->files->listFiles([
					'q'	=> "'$folderID' in parents and id='$fileID'",
					'spaces' => 'drive',
					'pageToken' => null,
					'fields' => 'files(id)',
				]);
				$fileID = isset($response->files[0]) ? $response->files[0]->id : null;
				log_error('Google Drive delete_file got file ID', $fileID);

				# Delete file
				if ($fileID)
					google_drive_service()->files->delete($fileID);
				return true;
			}
		} catch (Exception $e) {
			log_error('Google Drive delete_file error', $e->getMessage());
			return false;
		}
		return true;
	}

	# List all files in Google Drive (result can be big)
	function google_drive_list_files($baseParentFolderID, $folderName) {
		try {
			# Find folder ID
			$response = google_drive_service()->files->listFiles([
				'q'	=> sprintf("'%s' in parents and name='$folderName' and mimeType='application/vnd.google-apps.folder'", $baseParentFolderID),
				'spaces' => 'drive',
				'pageToken' => null,
				'fields' => 'files(id)',
			]);
			$folderID = isset($response->files[0]) ? $response->files[0]->id : null;
			log_error('Google Drive list_files got folder ID', $folderID);

			return google_drive_service()->files->listFiles([
				'q' => "'$folderID' in parents"
			]);
		} catch (Exception $e) {
			log_error('Google Drive list_files error', $e->getMessage());
		}
		return null;
	}
?>
