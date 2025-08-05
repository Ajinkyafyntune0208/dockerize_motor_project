import React, { useContext } from "react";
import { useAccordionToggle, Button } from "react-bootstrap";
import AccordionContext from "react-bootstrap/AccordionContext";
import styled from "styled-components";

const StyledButton = styled(Button)`
  display: flex;
  width: 20px;
  float: right;
  padding: 0;
  &:focus {
    box-shadow: none;
  }
  &&.btn-link {
    color: red;
    text-decoration: none;
  }
`;

const ContextAwareToggle = ({ children, eventKey, callback, disabled }) => {
  const currentEventKey = useContext(AccordionContext);

  const decoratedOnClick = useAccordionToggle(
    eventKey,
    () => callback && callback(eventKey)
  );

  const isCurrentEventKey = currentEventKey === eventKey;

  return (
    <StyledButton
      id={`accordionHeaderArrow${eventKey}`}
      variant="link"
      className="open-button"
      onClick={decoratedOnClick}
      style={{ display: disabled ? "none" : "block" }}
      aria-label={isCurrentEventKey ? "Collapse" : "Expand"}
    >
      {isCurrentEventKey ? (
        <i className="arrow up" aria-hidden="true"></i>
      ) : (
        <i className="arrow down" aria-hidden="true"></i>
      )}
    </StyledButton>
  );
};

export default ContextAwareToggle;
