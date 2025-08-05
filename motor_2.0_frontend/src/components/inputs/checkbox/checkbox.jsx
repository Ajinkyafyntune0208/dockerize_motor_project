import React from "react";
import PropTypes from "prop-types";
import "./checkbox.css";
import styled from "styled-components";
import CustomTooltip from "../../tooltip/CustomTooltip";
import tooltip from "../../../assets/img/tooltip.svg";
import { useMediaPredicate } from "react-media-hook";
const Checkbox = ({
  required,
  onChange,
  noWrapper,
  placeholder,
  placeholderSize,
  register,
  name,
  id,
  fieldName,
  index,
  tooltipData,
  color,
  ...otherProps
}) => {
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  //add on change
  const onChangeHandler = (ev) => {
    if (onChange) onChange(ev);
  };
  return (
    <>
      <FilterMenuBoxCheckConatiner color={color}>
        <div className="filterMenuBoxCheck">
          <input
            type="checkbox"
            className="form-check-input"
            id={id}
            value={id}
            ref={register}
            name={`${name}[${index}]`}
          />

          {tooltipData ? (
            <>
              <CustomTooltip
                rider="true"
                id={`${name}[${index}]tooltip`}
                place={"right"}
                customClassName="mt-3  "
              >
                <label
                  data-tip={`<h3 >${fieldName}</h3> <div>${tooltipData}</div>`}
                  data-html={true}
                  data-for={`${name}[${index}]tooltip`}
                  className="form-check-label"
                  htmlFor={id}
                >
                  {" "}
                  {fieldName}{" "}
                </label>
              </CustomTooltip>
              <span style={{ marginLeft: "3px" }}>
                {lessthan767 &&
                fieldName !== "PA cover for additional paid driver" ? (
                  <CustomTooltip
                    rider="true"
                    id={`${name}[${index}]tooltip_m`}
                    place={"left"}
                    customClassName="mt-3 "
                    allowClick
                  >
                    <img
                      data-tip={`<h3 >${fieldName}</h3> <div>${tooltipData}</div>`}
                      data-html={true}
                      data-for={`${name}[${index}]tooltip_m`}
                      src={tooltip}
                      alt="tooltip"
                      // className="toolTipRiderChild"
                    />
                  </CustomTooltip>
                ) : (
                  <noscript />
                )}
              </span>
            </>
          ) : (
            <label className="form-check-label" htmlFor={id}>
              {fieldName}{" "}
            </label>
          )}
        </div>
      </FilterMenuBoxCheckConatiner>
    </>
  );
};

Checkbox.defaultProps = {
  label: "",
  required: false,
  name: "",
  checked: false,
};

Checkbox.propTypes = {
  label: PropTypes.string,
  required: PropTypes.bool,
  name: PropTypes.string,
  type: PropTypes.string,
  onChange: PropTypes.func,
};

export default Checkbox;

const FilterMenuBoxCheckConatiner = styled.div`
  .filterMenuBoxCheck label {
    ${({ color }) =>
      color
        ? `font-size: 17px;
           color: #000000;
           font-weight: bold;
}`
        : ``};
  }
  .filterMenuBoxCheck input[type="checkbox"]:checked + label:before {
    background-color: ${({ theme, color }) =>
      color || theme.CheckBox?.color || "#bdd400"};
    border: ${({ theme, color }) =>
      color
        ? `1px solid ${color}`
        : theme.CheckBox?.border || "1px solid #bdd400"};
    box-shadow: ${({ theme }) =>
      theme.QuoteBorderAndFont?.shadowCheck || "none"};
    filter: ${({ theme }) =>
      theme.QuoteBorderAndFont?.filterPropertyCheckBox || "none"};
  }
`;
