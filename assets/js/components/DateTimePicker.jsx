import { useState } from 'react';
import { Button, DateTimePicker } from '@wordpress/components';
import { dateI18n, getDate, getSettings } from '@wordpress/date';
import { PopoverWrapper } from './PopoverWrapper';

const {
    formats: { 
        datetime: datetimeFormat,
        datetimeAbbreviated: datetimeAbbreviatedFormat 
    },
    l10n: { startOfWeek },
    timezone
} = getSettings();

/**
 * @param {Object} props
 * @param {HTMLInputElement} props.field - The field object containing the value.
 * @returns {JSX.Element}
 */
const APOS_DateTimePicker = ({field}) => {
    // Initialize the date state with the current 
    // value of the field or the current date
    const dateInit = Date.parse(field.value) 
        ? getDate(field.value)
        : getDate(null);

    const [ date, setDate ] = useState(dateInit);
    const [ popoverAnchor, setPopoverAnchor ] = useState();
    const [ isOpen, setIsOpen ] = useState(false);

    const handleDateChange = (newDate) => {
        setDate(newDate);

        // Store the date in ISO format for better timezone handling
        field.value = getDate(newDate).toISOString();
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    const toggleOpen = () => {
        setIsOpen(!isOpen);
    };

    return (
        <>
        <label 
            ref={setPopoverAnchor} 
            htmlFor={field.id} 
            style={{display:'block',fontWeight: 'bold', marginTop: '16px', marginBottom: '4px'}}>
            {field.dataset.label}
        </label>
        <Button 
            variant='tertiary' 
            onClick={toggleOpen}
            style={{ marginBottom: '4px' }}
        >
            {/* Use dateI18n instead of gmdateI18n to respect site timezone */}
            {dateI18n(datetimeFormat, date)}
        </Button>
        {isOpen && (
            <PopoverWrapper
                anchor={popoverAnchor}
                toggleOpen={toggleOpen}
                title={field.dataset.label}
            >
                <div style={{ padding: '16px' }}>
                    <DateTimePicker
                        currentDate={date}
                        onChange={handleDateChange}
                        startOfWeek={startOfWeek}
                        __nextRemoveHelpButton
                        __nextRemoveResetButton
                    />
                </div>
            </PopoverWrapper>
        )}
    </>
    );
};

export default APOS_DateTimePicker;