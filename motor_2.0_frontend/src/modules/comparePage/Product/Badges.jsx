import React from "react";
import { Badge } from "react-bootstrap";

const Badges = ({ title, name }) => {
  return (
    <Badge
      variant=""
      style={{
        cursor: "pointer",
        position: "relative",
      }}
      name={name}
    >
      {title}
    </Badge>
  );
};

export default Badges;
