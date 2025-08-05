import { CustomTooltip } from "components";
import React from "react";
import { useMediaPredicate } from "react-media-hook";
import PropTypes from "prop-types";
import { FilterMenuBoxCheckConatiner } from "./FeatureStyle";

const MenuCheckbox = ({
  id,
  value,
  onChange,
  defaultChecked,
  checked,
  t_id,
  onInput,
  t_text,
  data_for,
  htmlFor,
  v_value,
  extraSection,
  noMargin
}) => {
  const lessThan767 = useMediaPredicate("(max-width: 767px)");
  return (
    <FilterMenuBoxCheckConatiner>
      <div className="filterMenuBoxCheck">
        <input
          type="checkbox"
          className="form-check-input"
          id={id}
          value={value}
          defaultChecked={defaultChecked}
          checked={checked}
          onChange={onChange}
        />

        <CustomTooltip
          rider="true"
          id={t_id}
          place={"right"}
          customClassName="mt-3  "
        >
          <label
            style={{ zIndex: "9" }}
            onInput={onInput}
            data-tip={lessThan767 ? "" : t_text}
            data-html={true}
            data-for={data_for}
            className="form-check-label"
            htmlFor={htmlFor}
          >
            {v_value}
          </label>
        </CustomTooltip>
        {extraSection && extraSection}
        {!noMargin && <span style={{ marginLeft: "3px" }}></span>}
      </div>
    </FilterMenuBoxCheckConatiner>
  );
};

export default MenuCheckbox;

// PropTypes
MenuCheckbox.propTypes = {
    id: PropTypes.string,
    value: PropTypes.string,
    onChange: PropTypes.func,
    defaultChecked: PropTypes.bool,
    checked: PropTypes.bool,
    t_id: PropTypes.string,
    onInput: PropTypes.func,
    t_text: PropTypes.string,
    data_for: PropTypes.string,
    htmlFor: PropTypes.string,
    v_value: PropTypes.string,
    extraSection: PropTypes.object,
    noMargin: PropTypes.bool,
  };
  
  // DefaultTypes
  MenuCheckbox.defaultProps = {
    defaultChecked: false,
    checked: false,
    onInput: () => {},
    noMargin: false,
  };
  