import React from "react";
import styled from "styled-components";

const Wrapper = styled.div`
	flex: 1;
	display: flex;
	align-items: center;
	font-weight: ${(props) => (props?.quotes ? "800" : "400")};
`;

const AccordionHeader = (props) => {
	const { children, quotes } = props;

	return (
		<Wrapper style={{ cursor: "pointer" }} quotes={quotes}>
			{children}
		</Wrapper>
	);
};

export default AccordionHeader;
