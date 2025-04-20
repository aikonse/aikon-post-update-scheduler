import { createRoot } from 'react-dom/client';
import APOS_DateTimePicker from './components/DateTimePicker';

const picker = document.getElementById('apos-datetime-picker');
const field = document.getElementById('apos-datetime-picker-field');

if ( picker ) {
    const root = createRoot( picker );
    root.render( <APOS_DateTimePicker field={field} /> );
}
