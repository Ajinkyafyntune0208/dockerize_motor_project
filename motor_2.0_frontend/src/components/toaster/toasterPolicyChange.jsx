import React from "react";
import { toast } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import "./toaster.scss";
import { Bounce } from "react-toastify";
import { GlobalStyle, ToastMessage, ToastMessageContainer } from "./style";
import { useShowToastMessage } from "./toaster-hook";
toast.configure();

// toast component for edit details toaster
function ToasterPolicyChange({
  callToaster,
  setCall,
  content,
  Theme,
  setToasterShown,
}) {
  const notify = () => {
    toast(
      <ToastMessageContainer Theme={Theme}>
        <ToastMessage>{content} </ToastMessage>{" "}
        <div style={{ display: "flex", justifyContent: "flex-end" }}></div>{" "}
      </ToastMessageContainer>,
      {
        transition: Bounce,
        className: "toasterStyle",
        position: "top-right",
        autoClose: 5000,
        hideProgressBar: false,
        closeOnClick: false,
        pauseOnHover: true,
        draggable: false,
        progress: undefined,

        style: {
          position: "relative",
          top: Theme?.QuoteBorderAndFont?.toasterTop
            ? Theme?.QuoteBorderAndFont?.toasterTop
            : "125px",
          right: "70px",
          width: "300px",
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

export default ToasterPolicyChange;
