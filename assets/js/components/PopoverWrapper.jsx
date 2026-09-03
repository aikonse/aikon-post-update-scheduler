import { Button, Flex, FlexItem, Popover } from '@wordpress/components';
import { close } from '@wordpress/icons';

export const PopoverWrapper = ( { children, anchor, toggleOpen, title } ) => {
	return (
		<Popover
			headerTitle={ title }
			// position="left top"
			variant="default"
			anchor={ anchor }
		>
			<div style={ { padding: '16px' } }>
				<Flex>
					<FlexItem>
						<h3>{ title }</h3>
					</FlexItem>
					<FlexItem>
						<Button
							size="small"
							variant="link"
							icon={ close }
							onClick={ toggleOpen }
						/>
					</FlexItem>
				</Flex>
				{ children }
			</div>
		</Popover>
	);
};