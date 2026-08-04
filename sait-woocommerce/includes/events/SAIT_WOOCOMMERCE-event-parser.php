<?php

/**
 * Evento SAIT validado en la frontera REST.
 */
class SAIT_WOOCOMMERCE_Event
{
	private $type;
	private $xml;

	public function __construct($type, $xml)
	{
		$this->type = trim((string) $type);
		$this->xml = $xml;
	}

	/** @return string */
	public function type()
	{
		return $this->type;
	}

	/** @return SimpleXMLElement */
	public function xml()
	{
		return $this->xml;
	}
}

/**
 * Valida XML y produce un evento normalizado sin modificar campos SAIT.
 */
class SAIT_WOOCOMMERCE_EventParser
{
	/**
	 * @param string $body Cuerpo XML recibido.
	 * @return SAIT_WOOCOMMERCE_Event|WP_Error
	 */
	public function parse($body)
	{
		$previous = libxml_use_internal_errors(true);
		libxml_clear_errors();
		$xml = simplexml_load_string((string) $body);
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		if (!$xml) {
			return new WP_Error(
				'sait_invalid_xml',
				wp_json_encode($errors),
				array('status' => 500)
			);
		}

		$type = isset($xml['type']) ? (string) $xml['type'] : '';

		return new SAIT_WOOCOMMERCE_Event($type, $xml);
	}
}
