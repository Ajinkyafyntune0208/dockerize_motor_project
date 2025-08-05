import React from "react";
import PropTypes from "prop-types";
import {
  FormGroupInput,
  Input as FormInput,
  FormLabel,
  SpanLabel,
  Img,
} from "./style";

const Input = ({
  label,
  required,
  onChange,
  name,
  defaultValue,
  value,
  type,
  error,
  placeholder,
  inputRef,
  noWrapper,
  onBlur,
  isRequired,
  ...otherProps
}) => {
  // on change method
  const onChangeHandler = (e) => {
    if (onChange) onChange(e);
  };
  const onBlurHandler = (e) => {
    if (onBlur) onBlur(e);
  };

  const _renderInput = () => (
    <>
      <FormGroupInput className="form-group">
        <FormInput
          className={`${error ? "error" : ""}`}
          onChange={onChangeHandler}
          name={name}
          value={value || ""}
          defaultValue={defaultValue}
          type={type}
          placeholder={placeholder}
          required={required}
          inputRef={inputRef}
          onBlur={onBlurHandler}
          {...otherProps}
        />
        <FormLabel htmlFor="name">
          <SpanLabel>
            {label}
            {required || isRequired ? (
              <sup>
                {" "}
                <Img
                  alt="important"
                  src={`${
                    import.meta.env.VITE_BASENAME !== "NA"
                      ? `/${import.meta.env.VITE_BASENAME}`
                      : ""
                  }/assets/images/inputs/important.png`}
                />{" "}
              </sup>
            ) : null}
          </SpanLabel>
        </FormLabel>
      </FormGroupInput>
    </>
  );

  return <div className="form-group-input">{_renderInput()}</div>;
};

// ask for onChange default function !!
Input.defaultProps = {
  label: "label",
  value: "",
  placeholder: "placeholder",
  required: false,
  name: "",
  type: "text",
  // onChange: () => { },
};

Input.propTypes = {
  label: PropTypes.string,
  required: PropTypes.bool,
  name: PropTypes.string,
  onChange: PropTypes.func,
};

export default Input;
