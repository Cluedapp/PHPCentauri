<?php
	/**
	 * @package PHPCentauri
	 *
	 * Required Composer packages:
	 *    elasticsearch/elasticsearch
	 */

	function elastic_add($index, $type, $id, $data) {
		try {
			$client = elastic_client();
			$params = ['index' => $index, 'type' => $type, 'id' => $id, 'body' => $data];
			$response = $client->index($params);
			return $response;
		} catch (Exception $e) {
		}
	}

	function elastic_client() {
		static $client = null;
		try {
			if ($client === null) {
				$client = Elasticsearch\ClientBuilder::create()->build();
			}
		} catch (Exception $e) {
		}
		return $client;
	}

	function elastic_create_index($index_name) {
		try {
			$client = elastic_client();
			$params = ['index' => $index_name, 'body' => ['settings' => ['number_of_shards' => 2, 'number_of_replicas' => 0]]];
			$response = $client->indices()->create($params);
			return $response;
		} catch (Exception $e) {
		}
	}

	function elastic_delete($index, $type, $id) {
		try {
			$client = elastic_client();
			$params = ['index' => $index, 'type' => $type, 'id' => $id];
			$response = $client->delete($params);
			return $response;
		} catch (Exception $e) {
		}
	}

	function elastic_delete_index($index_name) {
		try {
			$client = elastic_client();
			$params = ['index' => $index_name];
			$response = $client->indices()->delete($params);
			return $response;
		} catch (Exception $e) {
		}
	}

	function elastic_get($index, $type, $id) {
		try {
			$client = elastic_client();
			$params = ['index' => $index, 'type' => $type, 'id' => $id];
			$response = $client->getSource($params);
			return $response;
		} catch (Exception $e) {
		}
	}

	function elastic_search($data) {
		try {
			$client = elastic_client();
			$params = ['index' => 'my_index', 'type' => 'my_type', 'body' => ['query' => ['match' => $data]]];
			$response = $client->search($params);
			return $response;
		} catch (Exception $e) {
		}
	}
?>
