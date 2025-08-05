import { CustomTooltip } from "components";
import React from "react";
import { Badge } from "react-bootstrap";

const OptionalTooltip = ({ id, top, name }) => {
  return (
    <CustomTooltip
      rider="true"
      id={id}
      place={"right"}
      customClassName="mt-3 riderPageTooltip "
    >
      <Badge
        data-tip="<div>Please also refer to the quote/premium page to  check the availability of the applicable Addons.</div>"
        data-html={true}
        data-for={id}
        variant=""
        style={{
          cursor: "pointer",
          top: top,
          position: "relative",
        }}
        name={name}
      >
        Optional*
      </Badge>
    </CustomTooltip>
  );
};

export default OptionalTooltip;
