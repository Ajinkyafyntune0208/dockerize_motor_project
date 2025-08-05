import React, { useEffect, useState } from "react";
import { Button } from "react-bootstrap";
import Camera from "react-html5-camera-photo";
import "react-html5-camera-photo/build/css/index.css";
import styled, { createGlobalStyle } from "styled-components";
import ReactCrop from "react-image-crop";
import "react-image-crop/src/ReactCrop.scss";
import swal from "sweetalert";

function CameraCapture(props) {
  const [img, setImg] = useState(null);
  const [crop, setCrop] = useState({ unit: "%", width: 30, aspect: 16 / 9 });
  const [edit, setEdit] = useState(false);
  const [error, setError] = useState(false);

  const extensionsString = props?.acceptedExt;
  const extensionsArray = extensionsString.match(/\b\w+\b/g);

  const filteredExt = extensionsArray.filter((ext) => {
    return ["jpg", "jpeg", "png"].includes(ext);
  });

  const extension = filteredExt[0];

  function handleTakePhoto(dataUri) {
    const byteCharacters = atob(dataUri.split(",")[1]);
    const byteNumbers = new Array(byteCharacters.length);
    for (let i = 0; i < byteCharacters.length; i++) {
      byteNumbers[i] = byteCharacters.charCodeAt(i);
    }
    const byteArray = new Uint8Array(byteNumbers);
    const blob = new Blob([byteArray], {
      type: `image/${extension}`,
    });

    const file = new File([blob], `profile_photo.${extension}`, {
      type: `image/${extension}`,
    });

    setImg(file);
  }

  useEffect(() => {
    if (error) {
      swal("Error", "An error occur while opening camera", "error");
      props.onClose();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [error]);

  const handleUpload = () => {
    props.setPhoto(img);
    props.setCropImage(false);
    document.getElementById("close").click();
  };
  const handleRetake = () => {
    setImg(null);
  };
  const handleEdit = () => {
    setEdit(true);
  };

  const handleImageCrop = async () => {
    const image = new Image();
    image.src = URL.createObjectURL(img);

    image.onload = () => {
      const canvas = document.createElement("canvas");
      const ctx = canvas.getContext("2d");
      canvas.width = crop.width;
      canvas.height = crop.height;

      ctx.drawImage(
        image,
        crop.x,
        crop.y,
        crop.width,
        crop.height,
        0,
        0,
        crop.width,
        crop.height
      );

      canvas.toBlob((blob) => {
        const croppedImageFile = new File(
          [blob],
          `profile_photo.${extension}`,
          {
            type: `image/${extension}`,
          }
        );

        setImg(croppedImageFile);
      }, `image/${extension}`);
    };
    setEdit(false);
    props.setCropImage(true);
  };

  return (
    <Container>
      {!edit && (
        <>
          {img ? (
            <div
              style={{ width: "100%" }}
              className="d-flex justify-content-center align-item-center"
            >
              <div>
                <img
                  height="450px"
                  width="100%"
                  src={URL.createObjectURL(img)}
                  alt=""
                  style={{ objectFit: "contain" }}
                />
                <Buttons>
                  <Button variant="success" onClick={handleUpload}>
                    <i className="fa fa-check"></i>
                  </Button>
                  <Button variant="info" onClick={handleEdit}>
                    <i className="fa fa-pencil-square-o"></i>
                  </Button>
                  <Button variant="danger" onClick={handleRetake}>
                    <i className="fa fa-times"></i>
                  </Button>
                </Buttons>
              </div>
            </div>
          ) : (
            <Camera
              style={{ width: "100%" }}
              onTakePhoto={(dataUri) => {
                handleTakePhoto(dataUri);
              }}
              onCameraError={(error) => {
                setError(true);
              }}
              isDisplayStartCameraError={(error) => {
                setError(true);
              }}
            />
          )}
        </>
      )}

      {edit && img && (
        <>
          <div className="d-flex justify-content-center align-items-center">
            <ReactCrop crop={crop} onChange={(c) => setCrop(c)}>
              <img src={URL.createObjectURL(img)} alt="" />
            </ReactCrop>
          </div>
          <Button
            className="saveIcon"
            variant="success"
            onClick={handleImageCrop}
          >
            <i className="fa fa-check"></i>
          </Button>
        </>
      )}
      <GlobalStyle />
    </Container>
  );
}

export default CameraCapture;

const GlobalStyle = createGlobalStyle`
 .react-html5-camera-photo  {
    height: 450px !important;
    overflow: hidden !important;
    @media (max-width: 768px) {
    height: 270px !important;
  }
    @media (max-width: 375px) {
    height: 230px !important;
  }
  }
  .react-html5-camera-photo>img, .react-html5-camera-photo>video {
    width: 100% !important;
  }
`;

const Container = styled.div`
  .saveIcon {
    position: absolute;
    z-index: 99999;
    right: 5px;
    top: 5px;
    border-radius: 50%;
  }
`;

const Buttons = styled.div`
  display: flex;
  justify-content: space-around;
  align-items: center;
  margin-top: -37px;
  padding: 0 10px;
  position: relative;
  top: -10px;
  .btn-info,
  .btn-danger,
  .btn-success {
    border-radius: 50% !important;
  }
`;
