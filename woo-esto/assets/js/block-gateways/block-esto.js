const esto_settings = window.wc.wcSettings.getSetting( 'esto_data', {} )

const esto_content = ( esto_settings ) => {
	return window.wp.htmlEntities.decodeEntities( esto_settings.description || '' )
}

const esto_icon = ( esto_settings ) => {
	return esto_settings.icon
		? window.wp.element.createElement(
			'img',
			{
				src: esto_settings.icon,
				style: {
					'margin-right': '20px',
					'max-height': '30px'
				}
			} )
		: ''
}

const esto_label = ( esto_settings ) => {
	return window.wp.element.createElement(
		'span',
		{
			style: {
				'width': '100%',
				'display': 'flex',
				'justify-content': 'space-between',
				'align-items': 'center',
				'gap': '10px'
			}
		},
		window.wp.htmlEntities.decodeEntities( esto_settings.title || '' ),
		esto_icon( esto_settings )
	);
}

window.wc.wcBlocksRegistry.registerPaymentMethod( {
	name: 'esto',
	label: Object( window.wp.element.createElement )( esto_label, esto_settings ),
	content: Object( window.wp.element.createElement )( esto_content, esto_settings ),
	edit: Object( window.wp.element.createElement )( esto_content, esto_settings ),
	canMakePayment: () => true,
	ariaLabel: esto_settings.title,
	supports: {
		features: esto_settings.supports,
	}
} )
