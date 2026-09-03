import { createRoot } from '@wordpress/element';
import AposDateTimePicker from './components/DateTimePicker';

const picker = document.getElementById( 'apos-datetime-picker' );
const field = document.getElementById( 'apos-datetime-picker-field' );

if ( picker ) {
	const root = createRoot( picker );
	root.render( <AposDateTimePicker field={ field } /> );
}
