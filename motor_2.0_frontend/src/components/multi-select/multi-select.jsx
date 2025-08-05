import React, { useMemo } from "react";
import Select from "react-select";
import { useMediaPredicate } from "react-media-hook";
import _ from "lodash";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

export default function AnimatedMulti({
  options,
  value,
  onChange,
  borderRadius,
  id,
  name,
  onBlur,
  ref,
  placeholder,
  closeOnSelect,
  isClearable,
  isMulti,
  errors,
  Styled,
  required,
  knowMore,
  quotes,
  customSearch,
  autoFocus,
  defaultMenuIsOpen,
  onClick,
  noBorder,
  onValueChange,
  quotePage,
  stepperSelect,
  sort,
  isSearchable,
  rto,
  shareQuote,
  proVal,
}) {
  const lessthan480 = useMediaPredicate("(max-width: 480px)");
  const lessthan360 = useMediaPredicate("(max-width: 360px)");
  const lessthan993 = useMediaPredicate("(max-width: 993px)");

  // styling
  const MemoizedStyle = useMemo(
    () => ({
      container: (styles) => ({
        ...styles,
        minWidth: quotePage && lessthan993 ? "100%" : "unset",
        position: quotePage && lessthan993 ? "absolute" : "relative",
        right: quotePage && lessthan993 ? "30px" : "",
        bottom: quotePage && lessthan993 ? "-35px" : "",
      }),
      control: (styles, { isFocused, isSelected }) => ({
        ...styles,
        backgroundColor: "white",
        borderColor: !(
          (!Array.isArray(value) || value?.length) &&
          (!errors || value?.length)
        )
          ? "#d43d3d"
          : isFocused || isSelected
          ? knowMore
            ? "#000"
            : Theme?.MultiSelect?.color
            ? Theme?.MultiSelect?.color
            : "#006600"
          : "hsl(0,0%,80%)",
        borderWidth: quotePage
          ? "0px"
          : isFocused || isSelected
          ? quotes
            ? "1px"
            : "2px"
          : !(
              (!Array.isArray(value) || value?.length) &&
              (!errors || value?.length)
            )
          ? quotes
            ? "1px"
            : "2px"
          : "1px",
        minHeight: knowMore
          ? quotes
            ? quotePage
              ? "34px !important"
              : "45px !important"
            : "48px"
          : stepperSelect
          ? "50px"
          : shareQuote
          ? "50px"
          : proVal
          ? "40px !important"
          : "60px",
        maxHeight: knowMore
          ? quotes
            ? quotePage
              ? "34px !important"
              : "45px !important"
            : "48px"
          : stepperSelect
          ? "50px"
          : "",
        boxShadow: "0",
        borderRadius: knowMore
          ? quotes
            ? "8px"
            : "50px"
          : borderRadius
          ? borderRadius
          : stepperSelect
          ? "12px"
          : "2.5px",
        fontSize: knowMore
          ? quotePage
            ? "11px"
            : "14px"
          : proVal
          ? "13px"
          : "18px",
        cursor: quotePage ? "pointer" : "text",
        "&:hover": {
          border: `${quotes ? (quotePage ? "0px" : "1px") : "2px"} solid  ${
            knowMore
              ? "#000"
              : Theme?.MultiSelect?.color
              ? Theme?.MultiSelect?.color
              : "#006600"
          }`,
        },
      }),
      menu: (provided) => ({
        ...provided,
        zIndex: 9999,
        border: `${quotePage ? "1px" : "2px"}  solid  ${
          knowMore
            ? "#000"
            : noBorder
            ? "#FFF"
            : Theme?.MultiSelect?.color
            ? Theme?.MultiSelect?.color
            : "#006600"
        }`,
        boxShadow: noBorder ? "0px 4px 26px 7px #dcdcdc" : "0",
        marginTop: "-1px",
        borderRadius: borderRadius
          ? borderRadius
          : stepperSelect
          ? "12px"
          : "0",
        ...(stepperSelect && { overflow: "hidden" }),
      }),
      multiValue: (styles) => ({
        ...styles,
        padding: "5px",
        fontSize: shareQuote
          ? "13px"
          : lessthan480
          ? quotes && sort
            ? "9px"
            : "19px"
          : knowMore
          ? "14px"
          : proVal
          ? "14px"
          : "21px",
        lineHeight: proVal ? "20px" : "25px",
        fontWeight: "500",
        color: "#666666",
        backgroundColor: "#ebeced",
      }),
      multiValueLabel: (styles) => ({
        ...styles,
        color: "#666666",
        cursor: "pointer",
      }),
      multiValueRemove: (style) => ({
        ...style,
        svg: {
          zoom: "1.7",
          "-moz-transform": "scale(1.7)",
          color: "#a5a5a5",
        },
        "&:hover": {
          backgroundColor: "transparent",
          cursor: "pointer",
          color: "#3c3f3f",
        },
      }),
      singleValue: (style) => ({
        ...style,
        maxWidth: quotes
          ? "65% !important"
          : lessthan360
          ? "184px"
          : lessthan480
          ? "228px"
          : "",
        textOverflow: "ellipsis",
        ...(lessthan480 && { fontSize: "16px" }),
        ...(lessthan480 && quotes && sort && { fontSize: "9px" }),
      }),
      option: (provided, state) => ({
        ...provided,
        // borderBottom: '1px dotted pink',
        ...(!shareQuote && {
          backgroundColor: state.isFocused ? "rgba(0,0,0,.05)" : "#FFFFFF",
        }),
        padding: "10px 20px",
        fontSize: lessthan480
          ? quotes && sort
            ? "9px"
            : lessthan480 && rto
            ? "13px"
            : "18px"
          : knowMore
          ? quotePage
            ? "11px"
            : "14px"
          : shareQuote
          ? "14px"
          : proVal
          ? "13px"
          : "20px",
        lineHeight: "25px",
        cursor: "pointer",
        ...(!shareQuote && { color: "#666666" }),
        paddingLeft: "22px",
        marginTop: "-4px",
        borderRadius: borderRadius ? borderRadius : "none",
      }),
      placeholder: (provided, { isSelected, isFocused }) => ({
        ...provided,
        color:
          isSelected || isFocused ? "#dbdbdb" : quotes ? "#00000" : "#666666",

        fontFamily: quotes && !lessthan480 ? "Inter-SemiBold" : "",
        ...(lessthan480 && { fontSize: "16px" }),
      }),
      indicatorsContainer: (provided, state) => ({
        ...provided,
        display: state.selectProps?.menuIsOpen ? "none" : "flex",
        svg: {
          zoom: quotePage ? "1" : "1.5",
          "-moz-transform": "scale(1.5)",
          color: "#a5a5a5",
        },
        //	display: quotePage ? "none" : "flex",
        borderRadius: borderRadius ? borderRadius : "none",
      }),
      valueContainer: (provided) => ({
        ...provided,
        padding: proVal ? "2px 10px 2px 5px" : "2px 10px 2px 17px",
        position: quotes ? "" : "",
        left: quotes ? "" : "",
        fontfamily: quotes ? "Inter-SemiBold" : "",
        fontsize: quotes ? "14px" : proVal ? "13px" : "",
      }),
    }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [value, errors, lessthan480]
  );

  const handleChange = (e) => {
    onChange(e);
    if (onClick) {
      onClick(e);
    }
    if (onValueChange) {
      onValueChange(e);
    }
  };

  return (
    <Select
      id={id || "1-ms"}
      defaultMenuIsOpen={defaultMenuIsOpen}
      autoFocus={autoFocus}
      required={required}
      closeMenuOnSelect={closeOnSelect ? true : false}
      isMulti={isMulti}
      options={options || []}
      value={value}
      onChange={(e) => handleChange(e)}
      ignoreAccents={false}
      name={name}
      onBlur={onBlur}
      avoidHighlightFirstOption
      onSelect={onChange}
      ref={ref}
      defaultValue={value}
      hideSelectedOptions={false}
      placeholder={placeholder || "Select..."}
      isClearable={isClearable}
      styles={Styled && MemoizedStyle}
      onClick={onClick}
      isSearchable={isSearchable}
    />
  );
}
