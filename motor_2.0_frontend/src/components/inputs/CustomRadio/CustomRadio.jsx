import React, { useState } from "react";
import PropTypes from "prop-types";
import "./CustomRadio.css";
import styled, { createGlobalStyle } from "styled-components";
const CustomRadio = ({
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
  items,
  setNewChecked,
  selected,
  ...otherProps
}) => {
  //add on change
  const [checked, setChecked] = useState(items[0]);
  const onChangeHandler = (data) => {
    //	setChecked(data);
    //	setNewChecked(data);
  };

  return (
    <StyledRadio>
      <input
        className="checkbox-tools"
        type="radio"
        value={id}
        ref={register}
        checked={selected === fieldName}
        name={`${name}[${index}]`}
        //onClick={() => setChecked(fieldName)}
      />
      <label
        className="for-checkbox-tools"
        htmlFor={id}
        onClick={() => setNewChecked(fieldName)}
      >
        {fieldName}
      </label>
      <GlobalStyle />
    </StyledRadio>
  );
};

CustomRadio.defaultProps = {
  label: "",
  required: false,
  name: "",
  checked: false,
};

CustomRadio.propTypes = {
  label: PropTypes.string,
  required: PropTypes.bool,
  name: PropTypes.string,
  type: PropTypes.string,
  onChange: PropTypes.func,
};

export default CustomRadio;

const GlobalStyle = createGlobalStyle`



`;

const StyledRadio = styled.span`
  ${({ theme }) =>
    theme?.fontFamily &&
    `
      .checkbox-tools:not(:checked) + label{
      font-family: ${theme?.fontFamily} !important;
      }
      `}

  .checkbox-tools:checked + label::before,
  .checkbox-tools:not(:checked) + label::before {
    background-color: ${({ theme }) => theme.QuotePopups?.color || "#bdd400"};
  }

  .checkbox-tools:checked + label {
    background-color: ${({ theme }) => theme.QuotePopups?.color || "#bdd400"};
    color: "#000";
  }
`;
