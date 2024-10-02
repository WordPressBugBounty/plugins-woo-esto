const esto_x_settings = window.wc.wcSettings.getSetting( 'esto_x_data', {} )

const esto_x_content = ( esto_x_settings ) => {
	if ( esto_x_settings.show_calculator ) {
		return window.wp.element.createElement(
			'div',
			{},
			window.wp.htmlEntities.decodeEntities( esto_x_settings.description || '' ),
			window.wp.element.RawHTML( {
				children: esto_x_settings.calculator,
				style: {
					'padding-top': '20px'
				}
			} )
		)
	}
	else {
		return window.wp.htmlEntities.decodeEntities( esto_x_settings.description || '' )
	}
}

const esto_x_icon = ( esto_x_settings ) => {
	return esto_x_settings.icon
		? window.wp.element.createElement(
			'img',
			{
				src: esto_x_settings.icon,
				style: {
					'margin-right': '20px',
					'max-height': '30px'
				}
			} )
		: ''
}

const esto_x_label = ( esto_x_settings ) => {
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
		window.wp.htmlEntities.decodeEntities( esto_x_settings.title || '' ),
		esto_x_icon( esto_x_settings )
	);
}

window.wc.wcBlocksRegistry.registerPaymentMethod( {
	name: 'esto_x',
	label: Object( window.wp.element.createElement )( esto_x_label, esto_x_settings ),
	content: Object( window.wp.element.createElement )( esto_x_content, esto_x_settings ),
	edit: Object( window.wp.element.createElement )( esto_x_content, esto_x_settings ),
	canMakePayment: () => true,
	ariaLabel: esto_x_settings.title,
	supports: {
		features: esto_x_settings.supports,
	}
} )
