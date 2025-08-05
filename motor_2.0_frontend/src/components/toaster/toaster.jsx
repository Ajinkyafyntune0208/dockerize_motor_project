import React from "react";
import { toast, cssTransition } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import "./toaster.scss";
import SecureLS from "secure-ls";
import ThemeObj from "modules/theme-config/theme-config";
import _ from "lodash";
import {
  GlobalStyle,
  ToastButton,
  ToastMessage,
  ToastMessageContainer,
} from "./style";
import { useShowToastMessage } from "./toaster-hook";
toast.configure();

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

// toast component for edit details toaster
function Toaster({
  callToaster,
  setCall,
  content,
  buttonText,
  setEdit,
  setToasterShown,
  noButton,
  autoClose,
}) {
  const Custom = cssTransition({
    enter: "bounce-bottom",
    exit: "slide-out-left",
  });

  const notify = () => {
    toast(
      <ToastMessageContainer Theme={Theme}>
        <ToastMessage>{content} </ToastMessage>{" "}
        {!noButton && (
          <div style={{ display: "flex", justifyContent: "flex-end" }}>
            <ToastButton
              onClick={() => setEdit(true)}
              className="btnText"
              Theme={Theme}
            >
              {buttonText}
            </ToastButton>
          </div>
        )}{" "}
      </ToastMessageContainer>,
      {
        transition: Custom,
        className: "toasterStyle",
        position: "top-left",
        autoClose: autoClose ? autoClose : 10000,
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
          left: "70px",
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

export default Toaster;
