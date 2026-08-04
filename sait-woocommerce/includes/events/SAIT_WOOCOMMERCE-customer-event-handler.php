<?php

/** Procesa eventos de clientes SAIT. */
class SAIT_WOOCOMMERCE_CustomerEventHandler
{
	public static function MODCLI($oXml){

		// Si no es cliente web omitir
		$emailtw = trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->flds[0],"emailtw"));
		if ($emailtw==""){
			return SAIT_UTILS::SAIT_response(200,"No es cliente web");
		}

		// Si ya existe el cliente no hacer nada
		$clave = SAIT_UTILS::SAIT_getClaves("clientes",trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->keys[0],"numcli")),null);
		if (isset($clave->wcid)) {
			// Buscar el cliente si el email es distinto cambiarlo
			$customer = new WC_Customer( $clave->wcid );
			$customer->get_email();
			if ($emailtw != $customer->get_email()){			
				$user_by_email = get_user_by('email',$emailtw);
				if ($user_by_email && $user_by_email->ID != $clave->wcid){
					return SAIT_UTILS::SAIT_response(200,"Correo ya asignado a otro usuario");
				}
				$customer->set_email( $emailtw );
				$customer->save();
				$mailer = WC()->mailer();
				/** @var WC_Email_Customer_New_Account $email */
				$email = $mailer->emails['WC_Email_Customer_New_Account'];
				$email->trigger($clave->wcid,null,true);
				return SAIT_UTILS::SAIT_response(200,"Cliente actualizado");
			}

			return SAIT_UTILS::SAIT_response(200,"Cliente ya existe");
		}
	
		// Si no existe el numcli pero el correo ya existe, ligar la clave
		$user_by_email = get_user_by('email',$emailtw);
		if ($user_by_email){
			SAIT_UTILS::SAIT_insertClaves("clientes",trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->keys[0],"numcli")),$user_by_email->ID);
			return SAIT_UTILS::SAIT_response(200,"Cliente ligado a usuario existente");
		}
	
		// woocommerce 9.3 requiere estas opciones
		update_option('woocommerce_registration_generate_password', 'yes');
		update_option('woocommerce_registration_generate_username', 'yes');
		// Registrar nuevo cliente
		$user_id = wc_create_new_customer( $emailtw  );
		if ( is_wp_error($user_id)) {
			 return SAIT_UTILS::SAIT_response(200,"Ya hay una cuenta registrada con ese correo");
    	}
		// Guardar en claves
		SAIT_UTILS::SAIT_insertClaves("clientes",trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->keys[0],"numcli")),$user_id);
		
		return SAIT_UTILS::SAIT_response(200,"Cli ADD");

	}
}
