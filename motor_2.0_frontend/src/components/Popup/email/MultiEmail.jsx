import React, { useState, useEffect } from "react";
import _ from "lodash";
import "./multiEmail.css";
import { createGlobalStyle } from "styled-components";

export const MultiEmail = ({
  register,
  name,
  setDisEmail,
  setValue,
  prefill,
}) => {
  const [emails, setEmails] = useState([]);
  const [value, setValues] = useState("");
  const [error, setError] = useState(null);

  //prefill
  useEffect(() => {
    if (prefill) setEmails([prefill]);
  }, [prefill]);

  //format API
  useEffect(() => {
    if (emails && !_.isEmpty(emails)) {
      let formatVar = emails;
      setValue("multiEmails", formatVar);
    } else {
      setValue("multiEmails", []);
    }
  }, [emails, setValue]);

  // handleing onchange
  const handleChange = (e) => {
    const MValue = e.target.value;
    if (MValue && isEmail(MValue)) {
      setDisEmail(false);
    }
    if (!isEmail(MValue) || !MValue) {
      setDisEmail(true);
    }
    setValues(e.target.value);
    setError(null);
  };

  // handleing onblue
  const handleOnBlur = (e) => {
    const NValue = e.target.value.trim();
    if (NValue && isValid(NValue)) {
      setEmails([...emails, NValue]);
      setValues("");
    }
    if ((!NValue || !isEmail(NValue)) && emails.length !== 0) {
      setDisEmail(false);
    } else {
      setDisEmail(true);
    }
  };

  // handleKey Down
  const handleKeyDown = (e) => {
    if (["Enter", "Tab", ","].includes(e.key)) {
      e.preventDefault();

      const NewValue = value.trim();

      if (NewValue && isValid(NewValue)) {
        setEmails([...emails, NewValue]);
        setValues("");
      }
      if (!isValid(NewValue) || !NewValue) {
        setDisEmail(true);
      }
    }
  };

  // handle delete
  const handleDelete = (item) => {
    setEmails(emails.filter((i) => i !== item));
  };

  // handle Paste
  const handlePaste = (e) => {
    e.preventDefault();

    var paste = e.clipboardData.getData("text");
    const NEmails = paste.match(/[\w\d\.-]+@[\w\d\.-]+\.[\w\d\.-]+/g);

    if (emails) {
      var toBeAdded = NEmails.filter((email) => !isInList(email));
      setEmails([...emails, ...toBeAdded]);
    }
  };

  const isValid = (email) => {
    let error = null;

    if (isInList(email)) {
      error = `${email} has already been added.`;
    }

    if (!isEmail(email)) {
      error = `${email} is not a valid email address.`;
    }

    if (error) {
      setError({ error });
      return false;
    }

    return true;
  };
  const isInList = (email) => {
    return emails.includes(email);
  };

  const isEmail = (email) => {
    return /[\w\d\.-]+@[\w\d\.-]+\.[\w\d\.-]+/.test(email);
  };

  if (emails.length !== 0 || (value && isEmail(value))) {
    setDisEmail(false);
  } else {
    setDisEmail(true);
  }

  return (
    <>
      <input
        className={"input " + (error && " has-error")}
        value={value}
        placeholder="Enter your emails"
        onKeyDown={handleKeyDown}
        onChange={handleChange}
        onPaste={handlePaste}
        onBlur={handleOnBlur}
      />
      {error && <p className="error">{error?.error}</p>}
      {emails.map((item) => (
        <div className="tag-item" key={item}>
          {item}
          <button
            type="button"
            className="button"
            onClick={() => handleDelete(item)}
          >
            &times;
          </button>
        </div>
      ))}
      <input ref={register} name={"multiEmails"} type="hidden" />
      <GlobalStyle />
    </>
  );
};

const GlobalStyle = createGlobalStyle`

  ${({ theme }) =>
    theme.fontFamily &&
    `.input{
  font-family : ${theme.fontFamily} !important; }`}
`;
