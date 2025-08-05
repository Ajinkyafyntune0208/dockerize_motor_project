import React from "react";
import { toast } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import { Bounce } from "react-toastify";
import "./toaster.scss";
import { useMediaPredicate } from "react-media-hook";
import {
  ButtonContainer,
  GlobalStyle,
  ToastButton,
  ToastMessage,
  ToastMessageContainer,
} from "./style";
import { useShowToastMessage } from "./toaster-hook";
toast.configure();

// toast component for edit details toaster

function ToasterOla({
  callToaster,
  setCall,
  setEdit,
  Theme,
  setToasterShown,
  setShareQuotesFromToaster,
}) {
  const lessthan993 = useMediaPredicate("(max-width: 993px)");
  const dismissAll = () => toast.dismiss();
  const Share = () => {
    setEdit(true);
    setShareQuotesFromToaster(true);
    dismissAll();
  };
  const notify = () => {
    toast(
      <ToastMessageContainer Theme={Theme}>
        <ToastMessage>
          Would you like to take documents from customer?
          <div style={{ fontSize: "12px" }}>
            (eg. RC copy, previous policy details){" "}
          </div>
        </ToastMessage>{" "}
        <ButtonContainer>
          <ToastButton onClick={() => Share()} Theme={Theme}>
            Yes
          </ToastButton>
          <ToastButton onClick={() => dismissAll()} Theme={Theme}>
            No
          </ToastButton>
        </ButtonContainer>{" "}
      </ToastMessageContainer>,
      {
        transition: Bounce,
        className: "toasterStyle",
        position: "top-left",
        autoClose: 10000,
        hideProgressBar: false,
        closeOnClick: false,
        pauseOnHover: true,
        draggable: true,
        progress: undefined,

        style: {
          position: "relative",
          top: Theme?.QuoteBorderAndFont?.toasterTop
            ? Theme?.QuoteBorderAndFont?.toasterTop
            : lessthan993
            ? "250px"
            : "120px",
          left: lessthan993 ? "0px" : "70px",
        },
      }
    );
  };

  useShowToastMessage(callToaster, notify, setCall, setToasterShown);

  return (
    <div>
      <GlobalStyle Theme={Theme} />
    </div>
  );
}

export default ToasterOla;
