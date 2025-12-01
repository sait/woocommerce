<?php
class SAITSettingsPage
{
    /**
     * Retiene los valores para ser usados en los callbacks
     */
    private $options;

    /**
     * Inicializacion.
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // Las configuracion se mostrara en el submenu de opciones de administracion.
        add_options_page(
            'Settings Admin', 
            'Configuración SAIT', 
            'manage_options', 
            'opciones_sait_page', 
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Se trae las opciones de SAIT
        $this->options = get_option( 'opciones_sait' );
        ?>
        <div class="wrap">
            <h1>Configuración SAIT</h1>
            <form method="post" action="options.php">
            <?php
                // Muestra los campos del grupo de opciones de SAIT
                settings_fields( 'opciones_sait_group' );
                do_settings_sections( 'opciones_sait_page' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Definimos el grupo de opciones
     */
    public function page_init()
    {        
        // Registramos las opciones de SAIT
        register_setting(
            'opciones_sait_group', // Option group
            'opciones_sait', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );
        // Se define la seccion de configuracion de SAIT Nube
        add_settings_section(
            'SAITNube', // ID
            'Configuración SAITNube', // Title
            array( $this, 'print_section_info' ), // Callback
            'opciones_sait_page' // Page
        );  
        // Campo ApiKey
        add_settings_field(
            'SAITNube_APIKey',  
            'Apikey',  
            array( $this, 'SAITNube_APIKey_callback' ),  
            'opciones_sait_page',  
            'SAITNube'  
        );      
        // Campo URL
        add_settings_field(
            'SAITNube_URL', 
            'URL', 
            array( $this, 'SAITNube_URL_callback' ), 
            'opciones_sait_page', 
            'SAITNube'
        );  
        
        // Campo PrecioLista
        add_settings_field(
            'SAITNube_AccessToken', 
            'Token de acceso a plugin', 
            array( $this, 'SAITNube_AccessToken_callback' ), 
            'opciones_sait_page', 
            'SAITNube'
        ); 
        // Campo TipoDoc
        add_settings_field(
            'SAITNube_TipoDoc', 
            'TipoDoc: P=pedidos Q=Cotizaciones', 
            array( $this, 'SAITNube_TipoDoc_callback' ), 
            'opciones_sait_page', 
            'SAITNube'
        ); 

		// Campo para activar o desactivar el modal de Sucursal
		add_settings_field(
			'SAITNube_Sucursal_enabled',
			'¿Seleccionar sucursal?',
			array( $this, 'SAITNube_Sucursal_enabled_callback' ),
			'opciones_sait_page',
			'SAITNube'
		);
		
        // Campo NumAlm
        add_settings_field(
            'SAITNube_NumAlm', 
            'NumAlm', 
            array( $this, 'SAITNube_NumAlm_callback' ), 
            'opciones_sait_page', 
            'SAITNube'
        ); 
		

		// Campo para activar o desactivar ocultar productos con precio en 0
		add_settings_field(
			'SAITNube_OcultarSinPrecio_enabled',
			'¿Ocultar Productos con Precio en 0?',
			array( $this, 'SAITNube_OcultarSinPrecio_enabled_callback' ),
			'opciones_sait_page',
			'SAITNube'
		);

		// Campo para activar o desactivar la opción ExistAlm
		add_settings_field(
			'SAITNube_ExistAlm_enabled',
			'¿Mostrar existencia de multiples almacenes?',
			array( $this, 'SAITNube_ExistAlm_enabled_callback' ),
			'opciones_sait_page',
			'SAITNube'
		);
		
		// Campo ExistAlm
        add_settings_field(
            'SAITNube_ExistAlm', 
            'Mostrar existencia de estos almacenes', 
            array( $this, 'SAITNube_ExistAlm_callback' ), 
            'opciones_sait_page', 
            'SAITNube'
        ); 
		// Campo para activar el monto minimo de compra
		add_settings_field(
			'SAITNube_MinimoCarrito_Enabled',
			'Activar Monto minimo de compra',
			array( $this, 'SAITNube_MinimoCarrito_enabled_callback' ),
			'opciones_sait_page',
			'SAITNube'
		);
		// Campo MinimoCarrito
        add_settings_field(
            'SAITNube_MinimoCarrito', 
            'Monto minimo de compra', 
            array( $this, 'SAITNube_MinimoCarrito_callback' ), 
            'opciones_sait_page', 
            'SAITNube'
        ); 
				
        // Campo TipoCambio
        add_settings_field(
            'SAITNube_TipoCambio', 
            'TipoCambio', 
            array( $this, 'SAITNube_TipoCambio_callback' ), 
            'opciones_sait_page', 
            'SAITNube'
        ); 
		// Campo para activar o desactivar las promociones en el carrito
		add_settings_field(
			'SAITNube_Promo_enabled',
			'Calculo de precios y promociones en el carrito',
			array( $this, 'SAITNube_Promo_enabled_callback' ),
			'opciones_sait_page',
			'SAITNube'
		);

        // Campo para activar o desactivar las promociones globales
		add_settings_field(
			'SAITNube_PromoGlobal_enabled',
			'Calculo de precios y promociones en la tienda',
			array( $this, 'SAITNube_PromoGlobal_enabled_callback' ),
			'opciones_sait_page',
			'SAITNube'
		);

        // Campo PrecioLista
        add_settings_field(
            'SAITNube_PrecioLista', 
            'PrecioLista: vacio=preciopub', 
            array( $this, 'SAITNube_PrecioLista_callback' ), 
            'opciones_sait_page', 
            'SAITNube'
        ); 
		// Campo para activar o desactivar enviar las observaciones del pedido a SAIT
		add_settings_field(
			'SAITNube_PedidoObs_enabled',
			'Enviar a SAIT las observaciones del pedido',
			array( $this, 'SAITNube_PedidoObs_enabled_callback' ),
			'opciones_sait_page',
			'SAITNube'
		);
		
		// Campo para activar o desactivar enviar la direccion de envio en el pedido
		add_settings_field(
			'SAITNube_PedidoDirenvio_enabled',
			'Enviar a SAIT la direccion de envio',
			array( $this, 'SAITNube_PedidoDirenvio_enabled_callback' ),
			'opciones_sait_page',
			'SAITNube'
		);
		
		// Campo para activar o desactivar las funciones personalizadas de la empresa
		add_settings_field(
			'SAITNube_FuncionPersonalizadaPedido_enabled',
			'Usar funcion personalizada de pedido',
			array( $this, 'SAITNube_FuncionPersonalizadaPedido_enabled_callback' ),
			'opciones_sait_page',
			'SAITNube'
		);
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['SAITNube_APIKey'] ) )
            $new_input['SAITNube_APIKey'] = sanitize_text_field( $input['SAITNube_APIKey'] );

        if( isset( $input['SAITNube_URL'] ) )
            $new_input['SAITNube_URL'] = sanitize_text_field( $input['SAITNube_URL'] );
        
        if( isset( $input['SAITNube_TipoDoc'] ) )
            $new_input['SAITNube_TipoDoc'] = sanitize_text_field( $input['SAITNube_TipoDoc'] );

        if( isset( $input['SAITNube_NumAlm'] ) )
            $new_input['SAITNube_NumAlm'] = sanitize_text_field( $input['SAITNube_NumAlm'] );
		
		if (isset($input['SAITNube_Sucursal_enabled'])) {
			$new_input['SAITNube_Sucursal_enabled'] = sanitize_text_field($input['SAITNube_Sucursal_enabled']);
		}
		
        if (isset($input['SAITNube_OcultarSinPrecio_enabled'])) {
			$new_input['SAITNube_OcultarSinPrecio_enabled'] = sanitize_text_field($input['SAITNube_OcultarSinPrecio_enabled']);
		}
        if (isset($input['SAITNube_ExistAlm_enabled'])) {
			$new_input['SAITNube_ExistAlm_enabled'] = sanitize_text_field($input['SAITNube_ExistAlm_enabled']);
		}
		if( isset( $input['SAITNube_ExistAlm'] ) )
            $new_input['SAITNube_ExistAlm'] = sanitize_text_field( $input['SAITNube_ExistAlm'] );	
		
        if (isset($input['SAITNube_MinimoCarrito_Enabled'])) {
			$new_input['SAITNube_MinimoCarrito_Enabled'] = sanitize_text_field($input['SAITNube_MinimoCarrito_Enabled']);
		}
		if( isset( $input['SAITNube_MinimoCarrito'] ) )
            $new_input['SAITNube_MinimoCarrito'] = sanitize_text_field( $input['SAITNube_MinimoCarrito'] );	
		
        if( isset( $input['SAITNube_TipoCambio'] ) )
            $new_input['SAITNube_TipoCambio'] = sanitize_text_field( $input['SAITNube_TipoCambio'] );
        
		if (isset($input['SAITNube_Promo_enabled'])) {
			$new_input['SAITNube_Promo_enabled'] = sanitize_text_field($input['SAITNube_Promo_enabled']);
		}

		if (isset($input['SAITNube_PromoGlobal_enabled'])) {
			$new_input['SAITNube_PromoGlobal_enabled'] = sanitize_text_field($input['SAITNube_PromoGlobal_enabled']);
		}
		
		if (isset($input['SAITNube_PedidoObs_enabled'])) {
			$new_input['SAITNube_PedidoObs_enabled'] = sanitize_text_field($input['SAITNube_PedidoObs_enabled']);
		}
		
		if (isset($input['SAITNube_PedidoDirenvio_enabled'])) {
			$new_input['SAITNube_PedidoDirenvio_enabled'] = sanitize_text_field($input['SAITNube_PedidoDirenvio_enabled']);
		}
		
		if (isset($input['SAITNube_FuncionPersonalizadaPedido_enabled'])) {
			$new_input['SAITNube_FuncionPersonalizadaPedido_enabled'] = sanitize_text_field($input['SAITNube_FuncionPersonalizadaPedido_enabled']);
		}
		
        if( isset( $input['SAITNube_PrecioLista'] ) )
            $new_input['SAITNube_PrecioLista'] = sanitize_text_field( $input['SAITNube_PrecioLista'] );
        
        if( isset( $input['SAITNube_AccessToken'] ) )
            $new_input['SAITNube_AccessToken'] = sanitize_text_field( $input['SAITNube_AccessToken'] );
        return $new_input;
    }
	

    /** 
     * Imprime el texto de la seccion
     */
    public function print_section_info()
    {
        print 'Llena los campos debajo:';
    }

    /** 
     * Obtiene el valor de la opcion y lo imprime
     */
    public function SAITNube_APIKey_callback()
    {
        printf(
            '<input type="text" id="SAITNube_APIKey" name="opciones_sait[SAITNube_APIKey]" value="%s" />',
            isset( $this->options['SAITNube_APIKey'] ) ? esc_attr( $this->options['SAITNube_APIKey']) : ''
        );
    }

    /** 
     * Obtiene el valor de la opcion y lo imprime
     */
    public function SAITNube_URL_callback()
    {
        printf(
            '<input type="text" id="SAITNube_URL" name="opciones_sait[SAITNube_URL]" value="%s" />',
            isset( $this->options['SAITNube_URL'] ) ? esc_attr( $this->options['SAITNube_URL']) : ''
        );
    }

    /** 
     * Obtiene el valor de la opcion y lo imprime
     */
    public function SAITNube_AccessToken_callback()
    {
        printf(
            '<input type="text" id="SAITNube_AccessToken" name="opciones_sait[SAITNube_AccessToken]" value="%s" />',
            isset( $this->options['SAITNube_AccessToken'] ) ? esc_attr( $this->options['SAITNube_AccessToken']) : ''
        );
    }

    /** 
     * Obtiene el valor de la opcion y lo imprime
     */
    public function SAITNube_TipoDoc_callback()
    {
        printf(
            '<input type="text" id="SAITNube_TipoDoc" name="opciones_sait[SAITNube_TipoDoc]" value="%s" />',
            isset( $this->options['SAITNube_TipoDoc'] ) ? esc_attr( $this->options['SAITNube_TipoDoc']) : ''
        );
    }

    /** 
     * Obtiene el valor de la opcion y lo imprime
     */
    public function SAITNube_TipoCambio_callback()
    {
        
        printf(
            '<input type="text" id="SAITNube_TipoCambio" name="opciones_sait[SAITNube_TipoCambio]" value="%s" readonly/>',
            isset( $this->options['SAITNube_TipoCambio'] ) ? esc_attr( $this->options['SAITNube_TipoCambio']) : ''
        );
        
    }

    /** 
     * Obtiene el valor de la opcion y lo imprime
     */
    public function SAITNube_NumAlm_callback()
    {
        printf(
            '<input type="text" id="SAITNube_NumAlm" name="opciones_sait[SAITNube_NumAlm]" value="%s" />',
            isset( $this->options['SAITNube_NumAlm'] ) ? esc_attr( $this->options['SAITNube_NumAlm']) : ''
        );
    }
	
    /** 
     * Obtiene el valor de la opcion y lo imprime
     */
    public function SAITNube_PrecioLista_callback()
    {
        printf(
            '<input type="text" id="SAITNube_PrecioLista" name="opciones_sait[SAITNube_PrecioLista]" value="%s" />',
            isset( $this->options['SAITNube_PrecioLista'] ) ? esc_attr( $this->options['SAITNube_PrecioLista']) : ''
        );
    }
	
	public function SAITNube_Promo_enabled_callback()
	{
		$value = isset($this->options['SAITNube_Promo_enabled']) ? $this->options['SAITNube_Promo_enabled'] : '0';
		?>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_Promo_enabled]" value="1" <?php checked('1', $value); ?> />
			Activado
		</label><br>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_Promo_enabled]" value="0" <?php checked('0', $value); ?> />
			Desactivado
		</label>
		<?php
	}


	public function SAITNube_PromoGlobal_enabled_callback()
	{
		$value = isset($this->options['SAITNube_PromoGlobal_enabled']) ? $this->options['SAITNube_PromoGlobal_enabled'] : '0';
		?>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_PromoGlobal_enabled]" value="1" <?php checked('1', $value); ?> />
			Activado
		</label><br>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_PromoGlobal_enabled]" value="0" <?php checked('0', $value); ?> />
			Desactivado
		</label>
		<?php
	}
	
	public function SAITNube_Sucursal_enabled_callback()
	{
		$value = isset($this->options['SAITNube_Sucursal_enabled']) ? $this->options['SAITNube_Sucursal_enabled'] : '0';
		?>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_Sucursal_enabled]" value="1" <?php checked('1', $value); ?> />
			Activado
		</label><br>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_Sucursal_enabled]" value="0" <?php checked('0', $value); ?> />
			Desactivado
		</label>
		<?php
		//$existAlm_activo = !empty($options['SAITNube_ExistAlm_enabled']) && $options['SAITNube_ExistAlm_enabled'] === '1';
	}
	

	public function SAITNube_OcultarSinPrecio_enabled_callback()
	{
		$value = isset($this->options['SAITNube_OcultarSinPrecio_enabled']) ? $this->options['SAITNube_OcultarSinPrecio_enabled'] : '0';
		?>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_OcultarSinPrecio_enabled]" value="1" <?php checked('1', $value); ?> />
			Activado
		</label><br>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_OcultarSinPrecio_enabled]" value="0" <?php checked('0', $value); ?> />
			Desactivado
		</label>
		<?php
		//$existAlm_activo = !empty($options['SAITNube_ExistAlm_enabled']) && $options['SAITNube_ExistAlm_enabled'] === '1';
	}

	public function SAITNube_ExistAlm_enabled_callback()
	{
		$value = isset($this->options['SAITNube_ExistAlm_enabled']) ? $this->options['SAITNube_ExistAlm_enabled'] : '0';
		?>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_ExistAlm_enabled]" value="1" <?php checked('1', $value); ?> />
			Activado
		</label><br>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_ExistAlm_enabled]" value="0" <?php checked('0', $value); ?> />
			Desactivado
		</label>
		<?php
		//$existAlm_activo = !empty($options['SAITNube_ExistAlm_enabled']) && $options['SAITNube_ExistAlm_enabled'] === '1';
	}
	
	 /** 
     * Obtiene el valor de la opcion y lo imprime
     */
    public function SAITNube_ExistAlm_callback()
    {
        printf(
            '<input type="text" id="SAITNube_ExistAlm" name="opciones_sait[SAITNube_ExistAlm]" value="%s" />',
            isset( $this->options['SAITNube_ExistAlm'] ) ? esc_attr( $this->options['SAITNube_ExistAlm']) : ''
        );
    }
	
	public function SAITNube_MinimoCarrito_enabled_callback()
	{
		$value = isset($this->options['SAITNube_MinimoCarrito_Enabled']) ? $this->options['SAITNube_MinimoCarrito_Enabled'] : '0';
		?>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_MinimoCarrito_Enabled]" value="1" <?php checked('1', $value); ?> />
			Activado
		</label><br>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_MinimoCarrito_Enabled]" value="0" <?php checked('0', $value); ?> />
			Desactivado
		</label>
		<?php
	}
	
	 /** 
     * Obtiene el valor de la opcion y lo imprime
     */
    public function SAITNube_MinimoCarrito_callback()
    {
        printf(
            '<input type="text" id="SAITNube_MinimoCarrito" name="opciones_sait[SAITNube_MinimoCarrito]" value="%s" />',
            isset( $this->options['SAITNube_MinimoCarrito'] ) ? esc_attr( $this->options['SAITNube_MinimoCarrito']) : ''
        );
    }
	
	public function SAITNube_PedidoObs_enabled_callback()
	{
		$value = isset($this->options['SAITNube_PedidoObs_enabled']) ? $this->options['SAITNube_PedidoObs_enabled'] : '0';
		?>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_PedidoObs_enabled]" value="1" <?php checked('1', $value); ?> />
			Activado
		</label><br>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_PedidoObs_enabled]" value="0" <?php checked('0', $value); ?> />
			Desactivado
		</label>
		<?php

	}
	
	public function SAITNube_PedidoDirenvio_enabled_callback()
	{
		$value = isset($this->options['SAITNube_PedidoDirenvio_enabled']) ? $this->options['SAITNube_PedidoDirenvio_enabled'] : '0';
		?>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_PedidoDirenvio_enabled]" value="1" <?php checked('1', $value); ?> />
			Activado
		</label><br>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_PedidoDirenvio_enabled]" value="0" <?php checked('0', $value); ?> />
			Desactivado
		</label>
		<?php
	}
	
	public function SAITNube_FuncionPersonalizadaPedido_enabled_callback()
	{
		$value = isset($this->options['SAITNube_FuncionPersonalizadaPedido_enabled']) ? $this->options['SAITNube_FuncionPersonalizadaPedido_enabled'] : '0';
		?>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_FuncionPersonalizadaPedido_enabled]" value="1" <?php checked('1', $value); ?> />
			Activado
		</label><br>
		<label>
			<input type="radio" name="opciones_sait[SAITNube_FuncionPersonalizadaPedido_enabled]" value="0" <?php checked('0', $value); ?> />
			Desactivado
		</label>
		<?php
	}

}

if( is_admin() )
    $SAIT_settings_page = new SAITSettingsPage();