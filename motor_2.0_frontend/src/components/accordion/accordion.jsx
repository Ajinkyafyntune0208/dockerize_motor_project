import React, { useState, useEffect, useRef } from "react";
import { Accordion, Card } from "react-bootstrap";
import styled from "styled-components";
import ContextAwareToggle from "./toggle";
import AccordionHeader from "./accordion-header";
import AccordionContent from "./accordion-content";

const StyledAccordion = styled(Accordion)`
	margin: 10px 0;
`;

const StyledCard = styled(Card)`
	border: ${(props) =>
		props?.disabled ? " 0px solid #cae9ff" : " 1px solid #ececec"};

	@media (max-width: 767px) {
		padding-bottom: 10px;
		border-radius: 0px;
		border-bottom: 1px solid #bebbbb !important;
	}
`;

const Header = styled(Card.Header)`
	border: none;
	background-color: #fff !important;
	font-family: ${({ theme }) =>
		theme?.regularFont?.fontFamily
			? theme?.regularFont?.fontFamily
			: "basier_squareregular"};
`;

const HeaderWrapper = styled.div`
	display: flex;
	flex-direction: row;
`;

const CustomAccordion = (props) => {
	const {
		id,
		children,
		defaultOpen,
		eventKey,
		setEventKey,
		openAll,
		setOpenAll,
		disabled,
	} = props;

	const [ids, setIds] = useState(id);
	useEffect(() => {
		if (eventKey) {
			//setIds(0);
			setIds(id);
		} else {
			setIds(id);
		}
	}, [eventKey]);

	const _render = () => {
		const headerChild = React.Children.map(children, (child) => {
			if (child.type === AccordionHeader) {
				return child;
			}
		});

		const contentChild = React.Children.map(children, (child) => {
			if (child.type === AccordionContent) {
				return child;
			}
		});

		useEffect(() => {
			if (openAll) {
				document.getElementById(`accordionHeaderArrow${ids}`).click();
				setOpenAll(false);
			}
		}, [openAll]);

		const _renderCard = () => {
			return (
				<StyledCard disabled={disabled}>
					<Header
						style={{ pointerEvents: disabled ? "none" : "cursor" }}
						onClick={() => {
							setEventKey(false);
							document.getElementById(`accordionHeaderArrow${ids}`).click();
						}}
						id="ContextAwareToggleRef"
					>
						<HeaderWrapper>
							{headerChild || ""}

							<ContextAwareToggle
								eventKey={ids}
								isOpen={eventKey}
								disabled={disabled}
							/>
						</HeaderWrapper>
					</Header>
					<Accordion.Collapse eventKey={ids}>
						<Card.Body style={{ padding: "0" }}>{contentChild || ""}</Card.Body>
					</Accordion.Collapse>
				</StyledCard>
			);
		};

		return defaultOpen ? (
			<StyledAccordion defaultActiveKey={id}>{_renderCard()}</StyledAccordion>
		) : (
			<StyledAccordion>{_renderCard()}</StyledAccordion>
		);
	};

	return _render();
};

export default CustomAccordion;
