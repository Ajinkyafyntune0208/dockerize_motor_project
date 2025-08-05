import styled from "styled-components";
import React, { useState, useEffect } from "react";
import Popup from "components/Popup/Popup";
import HighlightOffIcon from "@mui/icons-material/HighlightOff";
import { AiFillEye } from "react-icons/ai";

export default function FilePicker({
  register,
  id,
  name,
  watch,
  file,
  setFile,
  placeholder,
  lessthan768,
  isProfilePhotoApplicable,
}) {
  const [show, setShow] = useState(false);
  const [url, setUrl] = useState("");
  const onClose = () => {
    setShow(false);
  };

  useEffect(() => {
    file && setUrl(URL.createObjectURL(file));

    return () => file && URL.revokeObjectURL(URL.createObjectURL(file));
  }, [file]);

  const openPDFInNewTab = () => {
    window.open(url, "_blank");
  };

  const content = (
    <div style={{ height: "100%", width: "100%", display: "flex" }}>
      <img
        style={{
          margin: "auto",
          maxWidth: lessthan768 ? "100%" : "400px",
          maxHeight: "400px",
        }}
        src={url}
        alt="profile"
      />
    </div>
  );
  return (
    <Main className="">
      <Label id={id}>
        <StyledFileName>{file ? file?.name : placeholder}</StyledFileName>
        <input
          type="file"
          id={id}
          name={name}
          onChange={(e) => setFile(e.target.files[0])}
          accept=".jpg,.jpeg,.xlx,.xlsx,.pdf,.gif,.bitmap,.png"
        />
        {file && (
          <div
            style={{ marginLeft: "auto" }}
            onClick={() => setFile()}
          >
            <HighlightOffIcon />
          </div>
        )}
      </Label>
      {file && (
        <>
          {!["application/pdf", "image/xlsx"].includes(file?.type) ? (
            <StyledWrapper
              id="image-preview"
              onClick={() => setShow(true)}
              isProfilePhotoApplicable={isProfilePhotoApplicable}
            >
              <EyeIcon />
            </StyledWrapper>
          ) : (
            <span
              id="image-preview"
              onClick={openPDFInNewTab}
            >
              <EyeIcon />
            </span>
          )}
        </>
      )}
      <Popup
        top="40%"
        show={show}
        onClose={onClose}
        content={content}
        position="middle"
        height="400px"
        width="400px"
      />
    </Main>
  );
}
const Main = styled.div`
  display: flex;
  span {
    border: 1px solid #999;
    padding: 0.2rem 0.25rem;
    height: 33.6px;
    cursor: pointer;
  }
`;

const StyledWrapper = styled.span`
  width: ${({ isProfilePhotoApplicable }) =>
    isProfilePhotoApplicable && "86px"};
  display: flex;
  align-items: center;
  justify-content: flex-start;
`;

const EyeIcon = styled(AiFillEye)`
  width: 25px;
  height: auto;
  color: black;
`;

const Label = styled.label`
  padding: 0.2rem 0.25rem;
  font-size: 15px;
  font-weight: normal;
  display: flex;
  width: 100%;
  height: 33.6px;
  color: #495057;
  background-color: #fff;
  background-clip: padding-box;
  border: 1px solid #999;
  border-radius: 0;
  margin-bottom: 0px !important;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
  cursor: pointer;
  overflow: hidden;
  input {
    height: 0;
    width: 0;
    display: none;
  }
`;

const StyledFileName = styled.p`
  height: 100%;
  width: 100%;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
`;
