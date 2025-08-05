import React from "react";
import { Badge } from "react-bootstrap";
import PropTypes from "prop-types";

const Badges = ({ value }) => {
  return (
    <Badge
      variant="dark"
      style={{
        cursor: "pointer",
        position: "relative",
        marginLeft: "5px",
      }}
    >
      {value}
    </Badge>
  );
};

export default Badges;

// PropTypes
Badges.propTypes = {
  value: PropTypes.string,
};
