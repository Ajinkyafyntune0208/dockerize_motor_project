import React from "react";
import RemoveIcon from "@material-ui/icons/Remove";
import AddIcon from "@material-ui/icons/Add";
import { ColllapseAllContainer } from "../style";
import { Badge } from "react-bootstrap";

const Collapse = ({
  eventKey,
  setEventKey,
  setAccordionId,
  accordionId,
  setOpenAll,
}) => {
  return (
    <ColllapseAllContainer
      onClick={() => {
        if (!eventKey) {
          setEventKey(true);
          setAccordionId(accordionId + 1);
        } else {
          setOpenAll(true);
        }
      }}
    >
      <Badge
        variant="outline-dark"
        size="sm"
        style={{
          cursor: "pointer",
          border: "1px solid #6b6e71",
        }}
      >
        {eventKey ? (
          <AddIcon style={{ color: "#6b6e71", fontSize: "15px" }} />
        ) : (
          <RemoveIcon style={{ color: "#6b6e71", fontSize: "15px" }} />
        )}
      </Badge>
    </ColllapseAllContainer>
  );
};

export default Collapse;
