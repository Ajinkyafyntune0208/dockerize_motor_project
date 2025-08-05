import { AccordionContent, AccordionHeader, CustomAccordion } from "components";
import React from "react";

const AccordionWrapper = ({
  id,
  eventKey,
  setEventKey,
  openAll,
  setOpenAll,
  lessthan767,
  heading,
  content,
}) => {
  return (
    <CustomAccordion
      id={id}
      noPadding
      defaultOpen
      eventKey={eventKey}
      setEventKey={setEventKey}
      openAll={openAll}
      setOpenAll={setOpenAll}
      disabled={lessthan767 ? true : false}
    >
      <AccordionHeader quotes={lessthan767 ? true : false}>
        {heading}
      </AccordionHeader>

      <AccordionContent>{content}</AccordionContent>
    </CustomAccordion>
  );
};

export default AccordionWrapper;
