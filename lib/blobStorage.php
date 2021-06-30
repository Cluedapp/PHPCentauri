<?php
	/**
	 * @package PHPCentauri
	 *
	 * This library module is a wrapper for the Microsoft Azure Blob Storage database
	 *
	 * Required PHP extensions:
	 *    See https://github.com/Azure/azure-storage-blob-php
	 *    php_fileinfo.dll
	 *    php_mbstring.dll
	 *    php_openssl.dll
	 *    php_xsl.dll
	 *    php_curl.dll (optional)
	 *
	 * Required Composer packages:
	 *    microsoft/azure-storage-blob
	 */

	# Create new blob container
	function create_blob_container($blob_container_name, $create_container_options = null) {
		# $create_container_options = new \MicrosoftAzure\Storage\Blob\Models\CreateContainerOptions();
		get_blob_client()->createContainer($blob_container_name, $create_container_options);
	}

	function delete_blob_container($blob_container_name) {
		get_blob_client()->deleteContainer($blob_container_name);
	}

	function delete_blob($blob_container_name, $blob_name) {
		get_blob_client()->deleteBlob($blob_container_name, $blob_name);
	}

	function get_blob_client() {
		# use MicrosoftAzure\Storage\Common\ServiceException;
		return \MicrosoftAzure\Storage\Blob\BlobRestProxy::createBlobService(AppSettings['TableStorageConnectionString']);
	}

	# Get the contents of an existing blob object/file, in specified blob container
	function get_blob($blob_container_name, $blob_name) {
		return stream_get_contents(get_blob_client()->getBlob($blob_container_name, $blob_name)->getContentStream());
	}

	function list_blobs($blob_container_name, $blob_prefix = '') {
		$blobListOptions = new \MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions();
		$blobListOptions->setPrefix($blob_prefix);
		return get_blob_client()->listBlobs($blob_container_name, $blobListOptions);	
	}

	# "Create new" / "update existing" blob object/file, with specified blob content, in specified blob container
	function set_blob($blob_container_name, $blob_name, $blob_content) {
		get_blob_client()->createBlockBlob($blob_container_name, $blob_name, $blob_content);
	}
?>
