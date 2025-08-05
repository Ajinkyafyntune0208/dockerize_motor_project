import { CustomTooltip } from "components";
import React from "react";
import Style from "../style";

const Checkbox = ({
  id,
  value,
  defaultChecked,
  checked,
  data_tip,
  data_for,
  lable,
  onClick,
  style,
}) => {
  return (
    <Style.FilterMenuBoxCheckConatiner>
      <div className="filterMenuBoxCheck" style={style}>
        <input
          type="checkbox"
          className="form-check-input"
          id={id}
          value={value}
          defaultChecked={defaultChecked}
          checked={checked}
        />
        <CustomTooltip rider="true" place={"right"} customClassName="mt-3  ">
          <label
            data-tip={data_tip}
            data-html={true}
            data-for={data_for}
            className="form-check-label"
            htmlFor={id}
            onClick={onClick}
          >
            {lable}
          </label>
        </CustomTooltip>
        <span style={{ marginLeft: "3px" }}></span>
      </div>
    </Style.FilterMenuBoxCheckConatiner>
  );
};

export default Checkbox;
