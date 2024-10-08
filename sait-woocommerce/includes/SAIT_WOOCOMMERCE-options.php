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

        // Campo NumAlm
        add_settings_field(
            'SAITNube_NumAlm', 
            'NumAlm', 
            array( $this, 'SAITNube_NumAlm_callback' ), 
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
		
        // Campo PrecioLista
        add_settings_field(
            'SAITNube_PrecioLista', 
            'PrecioLista: vacio=preciopub', 
            array( $this, 'SAITNube_PrecioLista_callback' ), 
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
        
        if( isset( $input['SAITNube_TipoCambio'] ) )
            $new_input['SAITNube_TipoCambio'] = sanitize_text_field( $input['SAITNube_TipoCambio'] );
        
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

}

if( is_admin() )
    $SAIT_settings_page = new SAITSettingsPage();