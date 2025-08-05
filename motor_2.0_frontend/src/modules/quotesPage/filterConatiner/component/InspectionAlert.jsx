import React from "react";
import Style from "../style";

const InspectionAlert = ({ scrollPosition, Theme, tempData }) => {
  return (
    <Style.AlertCover
      scroll={
        scrollPosition >
        (Theme?.QuoteBorderAndFont?.scrollHeight
          ? Theme?.QuoteBorderAndFont?.scrollHeight
          : 78.4)
      }
    >
      {" "}
      Vehicle inspection is required as your previous policy is{" "}
      {tempData.policyType === "Not sure"
        ? "not available"
        : tempData?.policyType === "Third-party"
        ? "Third-party"
        : "expired"}
    </Style.AlertCover>
  );
};

export default InspectionAlert;
