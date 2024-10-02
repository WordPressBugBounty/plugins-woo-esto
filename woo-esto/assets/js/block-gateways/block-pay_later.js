const pay_later_settings = window.wc.wcSettings.getSetting( 'pay_later_data', {} )

const pay_later_content = ( pay_later_settings ) => {
	return window.wp.htmlEntities.decodeEntities( pay_later_settings.description || '' )
}

const pay_later_icon = ( pay_later_settings ) => {
	return pay_later_settings.icon
		? window.wp.element.createElement(
			'img',
			{
				src: pay_later_settings.icon,
				style: {
					'max-height': '30px',
					'margin-right': '20px'
				}
			} )
		: ''
}

const pay_later_label = ( pay_later_settings ) => {
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
		window.wp.htmlEntities.decodeEntities( pay_later_settings.title || '' ),
		pay_later_icon( pay_later_settings ),
	);
}

window.wc.wcBlocksRegistry.registerPaymentMethod( {
	name: 'pay_later',
	label: Object( window.wp.element.createElement )( pay_later_label, pay_later_settings ),
	content: Object( window.wp.element.createElement )( pay_later_content, pay_later_settings ),
	edit: Object( window.wp.element.createElement )( pay_later_content, pay_later_settings ),
	canMakePayment: () => true,
	ariaLabel: pay_later_settings.title,
	supports: {
		features: pay_later_settings.supports,
	}
} )
