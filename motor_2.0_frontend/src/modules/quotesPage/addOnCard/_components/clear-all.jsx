import React from "react";
import { ClearAllButton } from "../style";
import { HiOutlineRefresh } from "react-icons/hi";

const ClearAll = ({ clearButtonCondition, setClearAll, clearAll }) => {
  return (
    <ClearAllButton
      style={{ visibility: clearButtonCondition ? "visible" : "hidden" }}
      id="clearAllAddons"
      onClick={() => {
        setClearAll(clearAll + 1);
      }}
    >
      Clear all
      <HiOutlineRefresh className="clearImage" />
    </ClearAllButton>
  );
};

export default ClearAll;
