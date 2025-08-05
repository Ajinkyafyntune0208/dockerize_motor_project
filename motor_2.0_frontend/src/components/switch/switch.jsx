import React, { useState, useEffect } from "react";
import PropTypes from "prop-types";
import { useSelector } from "react-redux";

import {
  InputBorder,
  CustomControl,
  SwitchContainer,
  SwitchInput,
  ToggleValues,
} from "./style";

const Switch = ({
  onChange,
  value,
  label,
  dark,
  Content,
  lessthan600,
  consent,
}) => {
  const [isChecked, setIsChecked] = useState(false);
  const { theme_conf } = useSelector((state) => state.home);

  const handleChange = (e) => {
    if (onChange) onChange(Number(e.target.checked));
    setIsChecked(!isChecked);
  };
  useEffect(() => {
    if (value) {
      setIsChecked(true);
    } else {
      setIsChecked(false);
    }
  }, [value]);

  return (
    <>
      <InputBorder consent={consent}>
        <CustomControl
          gst_text_color={
            theme_conf?.broker_config?.gst_style === "notFromTheme"
              ? theme_conf?.broker_config?.gst_text_color
              : false
          }
        >
          <SwitchContainer>
            <ToggleValues
              onClick={() =>
                document.getElementById("toggle") &&
                document.getElementById("toggle").click()
              }
            >
              {Content}
            </ToggleValues>
            <label>
              <SwitchInput
                consent={consent}
                dark={dark}
                gst_color={
                  theme_conf?.broker_config?.gst_style === "notFromTheme"
                    ? theme_conf?.broker_config?.gst_color
                    : false
                }
                gst_color_no={
                  theme_conf?.broker_config?.gst_style === "notFromTheme"
                    ? theme_conf?.broker_config?.gst_color_no
                    : false
                }
                checked={isChecked}
                // checked={false}
                onChange={handleChange}
                type="checkbox"
                id="toggle"
              />
              <div>
                <div style={{ transition: "all 0.5s" }}></div>
              </div>
            </label>
          </SwitchContainer>

          {!consent && (
            <span>
              {isChecked ? (
                <span
                  className="toggleTextNo"
                  style={{
                    fontWeight: "500",
                    fontSize: "12px",
                  }}
                  onClick={() => {
                    document.getElementById(`toggle`).click();
                  }}
                >
                  YES
                </span>
              ) : (
                <span
                  className="toggleTextYes"
                  style={{
                    fontWeight: "500",
                    fontSize: "12px",
                  }}
                  onClick={() => {
                    document.getElementById(`toggle`).click();
                  }}
                >
                  NO
                </span>
              )}
            </span>
          )}
        </CustomControl>

        {/* top label */}
      </InputBorder>
    </>
  );
};

// default props
Switch.defaultProps = {
  value: 0,
  label: "",
};

// props types
Switch.propTypes = {
  onChange: PropTypes.func,
  value: PropTypes.number,
};

export default Switch;
