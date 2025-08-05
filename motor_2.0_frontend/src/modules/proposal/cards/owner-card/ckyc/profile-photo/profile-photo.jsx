import { useState, useEffect } from "react";
import { Col, Form } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import { ErrorMsg } from "components";
import styled from "styled-components";
import FilePicker from "components/filePicker/filePicker";
// import { AiFillCamera } from "react-icons/ai";
// import CameraCapture from "../../../../image-capture/captureImage";
// import Popup from "components/Popup/Popup";

export const ProfilePhoto = ({
  temp_data,
  fields,
  ckycValue,
  uploadFile,
  photo,
  setPhoto,
  lessthan768,
  acceptedExt,
  fileUploadError,
  fileValidationText,
  watch,
  register,
}) => {
  const [cam, setCam] = useState(false);
  const [cropImage, setCropImage] = useState(false);

  // handle camera width
  useEffect(() => {
    if (cam) {
      setCropImage(false);
    }
  }, []);

  const handleCam = () => {
    setCam(false);
  };

  const companyAlias = temp_data?.selectedQuote?.companyAlias;
  const isProfilePhotoApplicable =
    fields?.includes("ckyc") &&
    fields.includes("photo") &&
    ((!(companyAlias === "shriram" && ckycValue === "YES") &&
      !["iffco_tokio", "sbi", "magma"].includes(companyAlias)) ||
      (["iffco_tokio", "sbi", "magma"].includes(companyAlias) &&
        uploadFile &&
        ckycValue === "NO"));

  return (
    <>
      {isProfilePhotoApplicable && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <div className="py-2">
            <FormGroupTag mandatory>Upload profile photo </FormGroupTag>
            <CamIcon
              onClick={() => {
                setCam(true);
              }}
            >
              {/* <AiFillCamera /> */}
            </CamIcon>
            <FilePicker
              file={photo}
              setFile={setPhoto}
              watch={watch}
              register={register}
              name={"photo"}
              id={"photo"}
              placeholder={"Upload your photo here"}
              lessthan768={lessthan768}
              isProfilePhotoApplicable={isProfilePhotoApplicable}
            />
            {!photo && fileUploadError ? (
              <ErrorMsg fontSize={"12px"}>Please Upload Photo</ErrorMsg>
            ) : (
              <Form.Text className="text-muted">
                <text style={{ color: "#bdbdbd" }}>{fileValidationText}</text>
              </Form.Text>
            )}
          </div>
        </Col>
      )}
      {/* <Popup
        width={cropImage ? "auto" : "600px"}
        height="450px"
        onClose={handleCam}
        show={cam}
        content={
          <CameraCapture
            setPhoto={setPhoto}
            onClose={handleCam}
            cropImage={cropImage}
            setCropImage={setCropImage}
            companyAlias={companyAlias}
            acceptedExt={acceptedExt}
          />
        }
      /> */}
    </>
  );
};

const CamIcon = styled.span`
  position: absolute;
  top: 31px;
  right: 0;
  margin-right: 20px;
  color: black;
  cursor: pointer;
  font-size: 25px;
`;
