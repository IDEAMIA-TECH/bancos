// Belvo SDK Integration
const belvoSDK = {
    createWidget: function(options) {
        // Cargar el SDK de Belvo
        const script = document.createElement('script');
        script.src = 'https://cdn.belvo.io/belvo-widget-1-stable.js';
        script.async = true;
        document.body.appendChild(script);

        script.onload = function() {
            // Inicializar el widget cuando el SDK esté cargado
            window.belvoSDK.createWidget({
                institution: options.institution,
                callback: function(link) {
                    options.callback(link);
                },
                locale: 'es',
                country_codes: ['MX'],
                onExit: function(data) {
                    console.log('Widget closed:', data);
                },
                onError: function(error) {
                    console.error('Widget error:', error);
                    alert('Error al conectar con el banco: ' + error.message);
                }
            });
        };
    }
}; 