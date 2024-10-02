const esto_pay_settings = window.wc.wcSettings.getSetting( 'esto_pay_data', {} )

const { useEffect } = window.wp.element;

const esto_pay_content = ( props ) => {

	if ( esto_pay_settings.show_bank_logos ) {

		const { eventRegistration, emitResponse } = props;
		const { onPaymentSetup } = eventRegistration;
		useEffect( () => {
			const esto_pay_bank_selected = onPaymentSetup( async () => {
				const esto_pay_bank_selection = document.getElementsByName( 'esto_pay_bank_selection' )[0].value;
				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							'esto_pay_bank_selection': esto_pay_bank_selection,
						},
					},
				};
			} );

			return () => {
				esto_pay_bank_selected();
			};
		}, [
			emitResponse.responseTypes.SUCCESS,
			onPaymentSetup,
		] );

		return window.wp.element.createElement(
			'div',
			{},
			window.wp.htmlEntities.decodeEntities( esto_pay_settings.description || '' ),
			window.wp.element.RawHTML( {
				children: esto_pay_settings.bank_logos,
				style: {
					'padding-top': '20px'
				}
			} )
		)
	}
	else {
		return window.wp.htmlEntities.decodeEntities( esto_pay_settings.description || '' )
	}
}

const esto_pay_icon = () => {
	return esto_pay_settings.icon
		? window.wp.element.createElement(
			'img',
			{
				src: esto_pay_settings.icon,
				style: {
					'margin-right': '20px',
					'max-height': '30px'
				}
			} )
		: ''
}

const esto_pay_label = () => {
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
		window.wp.htmlEntities.decodeEntities( esto_pay_settings.title || '' ),
		esto_pay_icon( esto_pay_settings )
	);
}

window.wc.wcBlocksRegistry.registerPaymentMethod( {
	name: 'esto_pay',
	label: Object( window.wp.element.createElement )( esto_pay_label ),
	content: Object( window.wp.element.createElement )( esto_pay_content ),
	edit: Object( window.wp.element.createElement )( esto_pay_content ),
	canMakePayment: () => true,
	ariaLabel: esto_pay_settings.title,
	supports: {
		features: esto_pay_settings.supports,
	},
} )
