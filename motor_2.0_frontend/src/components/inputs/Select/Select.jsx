import React from "react";
import PropTypes from "prop-types";
import {
  FormGroupSelect,
  SelectInput,
  SelectLabel,
  SelectSpan,
  Img,
} from "./style";

const Select = ({
  id,
  label,
  options,
  option,
  customOptions,
  customIdOptions,
  optionKey,
  optionKeyOne,
  optionKeyTwo,
  required,
  onChange,
  name,
  value,
  data,
  placeholder,
  error,
  isRequired,
  disabled,
  singleSelected,
}) => {
  //on change method
  let onChangeHandler = (e) => {
    if (onChange) onChange(e);
  };

  return (
    <>
      <FormGroupSelect>
        <SelectInput
          className={error ? "error" : ""}
          // value={value}
          {...(!!data ? { value: data } : { value: value })}
          name={name}
          onChange={onChangeHandler}
          required={required}
          disabled={disabled}
        >
          {/* option map */}
          {option
            ? option.map((value) => (
                <option
                  selected={singleSelected ? true : false}
                  key={id + value}
                  value={value}
                >
                  {value}
                </option>
              ))
            : null}
          {options ? (
            <>
              <option key={`select-${id}`} value="">
                {placeholder}
              </option>
              )
              {options.map((item) => (
                <option key={item.id} value={item.value || item.id}>
                  {item.name}
                </option>
              ))}
            </>
          ) : null}
          {customOptions ? (
            <>
              <option key={`select-${id}`}>{placeholder}</option>
              {customOptions?.map((item, index) => (
                <option
                  key={`${optionKey}--${index}--${item.id}`}
                  value={item[optionKey]}
                >
                  {item[optionKey]}
                </option>
              ))}
            </>
          ) : null}
          {customIdOptions ? (
            <>
              <option key={`select-${id}`}>{placeholder}</option>
              {customIdOptions?.map((item, index) => (
                <option
                  key={`${optionKeyTwo}--${index}--${item.id}`}
                  value={item[optionKeyOne]}
                >
                  {item[optionKeyTwo]}
                </option>
              ))}
            </>
          ) : null}
        </SelectInput>
        {/*  top span*/}
        <SelectLabel htmlFor="name">
          <SelectSpan>
            {label}
            {required || isRequired ? (
              <sup>
                {" "}
                <Img
                  alt="Image Not Found"
                  src={`${
                    import.meta.env.VITE_BASENAME !== "NA"
                      ? `/${import.meta.env.VITE_BASENAME}`
                      : ""
                  }/assets/images/inputs/important.png`}
                />{" "}
              </sup>
            ) : null}
          </SelectSpan>
        </SelectLabel>
      </FormGroupSelect>
    </>
  );
};

Select.defaultProps = {
  label: "label",
  required: true,
  id: "default",
  disabled: false,
  // value: "",
};

Select.propTypes = {
  label: PropTypes.string,
  option: PropTypes.array,
  required: PropTypes.bool,
  name: PropTypes.string,
  value: PropTypes.string,
  onChange: PropTypes.func,
  id: PropTypes.string,
  disabled: PropTypes.bool,
};

export default Select;
