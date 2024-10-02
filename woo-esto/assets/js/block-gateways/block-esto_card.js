const esto_card_settings = window.wc.wcSettings.getSetting( 'esto_card_data', {} )

const esto_card_content = ( esto_card_settings ) => {
	return window.wp.htmlEntities.decodeEntities( esto_card_settings.description || '' )
}

const esto_card_icon = ( esto_card_settings ) => {
	return esto_card_settings.icon
		? window.wp.element.createElement(
			'img',
			{
				src: esto_card_settings.icon,
				style: {
					'margin-right': '20px',
					'max-height': '30px'
				}
			} )
		: ''
}

const esto_card_label = ( esto_card_settings ) => {
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
		window.wp.htmlEntities.decodeEntities( esto_card_settings.title || '' ),
		esto_card_icon( esto_card_settings )
	);
}

window.wc.wcBlocksRegistry.registerPaymentMethod( {
	name: 'esto_card',
	label: Object( window.wp.element.createElement )( esto_card_label, esto_card_settings ),
	content: Object( window.wp.element.createElement )( esto_card_content, esto_card_settings ),
	edit: Object( window.wp.element.createElement )( esto_card_content, esto_card_settings ),
	canMakePayment: () => true,
	ariaLabel: esto_card_settings.title,
	supports: {
		features: esto_card_settings.supports,
	}
} )
