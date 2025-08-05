/* eslint-disable jsx-a11y/anchor-is-valid */
import { ContactFn } from "components";
import React from "react";
import Styled from "../quotesStyle";
import { useLocation } from "react-router";

export const MobileBottomDrawer = ({
  popupOpen,
  addonDrawer,
  compare,
  toggleDrawer,
  setSendQuotes,
  theme_conf,
  quoteComprehesiveGrouped1,
  tab,
  setMobileComp,
  quoteComprehesive,
  quotetThirdParty,
  quoteShortTerm,
  quotesLoaded,
}) => {
  const location = useLocation();
  const loc = location.pathname ? location.pathname.split("/") : "";

  const extPath = `${
    import.meta.env.VITE_BASENAME !== "NA"
      ? `/${import.meta.env.VITE_BASENAME}`
      : ""
  }`;

  return (
    <Styled.BottomTabsContainer
      style={{
        display: popupOpen || addonDrawer || compare ? "none" : "flex",
      }}
    >
      <Styled.MobileFilterButtons onClick={toggleDrawer("left", true)}>
        <a className="TabBar__StyledLink-sc-cvgqr0-0 exoIhE">
          <div className="TabBar___StyledDiv-sc-cvgqr0-5 gPutWC">
            <svg
              stroke="currentColor"
              fill="currentColor"
              stroke-width="0"
              viewBox="0 0 512 512"
              height="1em"
              width="1em"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                fill="none"
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="32"
                d="M35.4 87.12l168.65 196.44A16.07 16.07 0 01208 294v119.32a7.93 7.93 0 005.39 7.59l80.15 26.67A7.94 7.94 0 00304 440V294a16.07 16.07 0 014-10.44L476.6 87.12A14 14 0 00466 64H46.05A14 14 0 0035.4 87.12z"
              ></path>
            </svg>
          </div>
          <div
            className="TabBar___StyledDiv2-sc-cvgqr0-6 jeDtVb"
            style={{ fontSize: "12px" }}
          >
            Addons
          </div>
        </a>
      </Styled.MobileFilterButtons>

      <Styled.MobileFilterButtons
        onClick={() =>
          ((((quoteComprehesive && quoteComprehesive.length >= 1) ||
            (quotetThirdParty && quotetThirdParty.length >= 1) ||
            (quoteShortTerm && quoteShortTerm.length >= 1)) &&
            !quotesLoaded) ||
            loc[2] === "proposal-page" ||
            loc[2] === "compare-quote" ||
            loc[1] === "payment-success") &&
          setSendQuotes(true)
        }
      >
        <a
          className={`TabBar__StyledLink-sc-cvgqr0-0 exoIhE ${
            !(
              (((quoteComprehesive && quoteComprehesive.length >= 1) ||
                (quotetThirdParty && quotetThirdParty.length >= 1) ||
                (quoteShortTerm && quoteShortTerm.length >= 1)) &&
                !quotesLoaded) ||
              loc[2] === "proposal-page" ||
              loc[2] === "compare-quote" ||
              loc[1] === "payment-success"
            )
              ? "disabled"
              : ""
          }`}
        >
          <div className="TabBar___StyledDiv-sc-cvgqr0-5 gPutWC">
            <svg
              stroke="currentColor"
              fill="currentColor"
              stroke-width="0"
              viewBox="0 0 24 24"
              height="1em"
              width="1em"
              xmlns="http://www.w3.org/2000/svg"
            >
              <circle fill="none" cx="17.5" cy="18.5" r="1.5"></circle>
              <circle fill="none" cx="5.5" cy="11.5" r="1.5"></circle>
              <circle fill="none" cx="17.5" cy="5.5" r="1.5"></circle>
              <path d="M5.5,15c0.91,0,1.733-0.358,2.357-0.93l6.26,3.577C14.048,17.922,14,18.204,14,18.5c0,1.93,1.57,3.5,3.5,3.5 s3.5-1.57,3.5-3.5S19.43,15,17.5,15c-0.91,0-1.733,0.358-2.357,0.93l-6.26-3.577c0.063-0.247,0.103-0.502,0.108-0.768l6.151-3.515 C15.767,8.642,16.59,9,17.5,9C19.43,9,21,7.43,21,5.5S19.43,2,17.5,2S14,3.57,14,5.5c0,0.296,0.048,0.578,0.117,0.853L8.433,9.602 C7.808,8.64,6.729,8,5.5,8C3.57,8,2,9.57,2,11.5S3.57,15,5.5,15z M17.5,17c0.827,0,1.5,0.673,1.5,1.5S18.327,20,17.5,20 S16,19.327,16,18.5S16.673,17,17.5,17z M17.5,4C18.327,4,19,4.673,19,5.5S18.327,7,17.5,7S16,6.327,16,5.5S16.673,4,17.5,4z M5.5,10C6.327,10,7,10.673,7,11.5S6.327,13,5.5,13S4,12.327,4,11.5S4.673,10,5.5,10z"></path>
            </svg>
          </div>
          <div
            className="TabBar___StyledDiv2-sc-cvgqr0-6 jeDtVb"
            style={{ fontSize: "12px" }}
          >
            Share
          </div>
        </a>
      </Styled.MobileFilterButtons>

      <Styled.MobileFilterButtons>
        <a
          className="TabBar__StyledLink-sc-cvgqr0-0 exoIhE"
          href={`tel:${theme_conf?.broker_config?.phone || ContactFn()}`}
        >
          <div className="TabBar___StyledDiv-sc-cvgqr0-5 gPutWC">
            <svg
              stroke="currentColor"
              fill="currentColor"
              stroke-width="0"
              viewBox="0 0 24 24"
              height="1em"
              width="1em"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                fill="none"
                d="M16.585,19.999l2.006-2.005l-2.586-2.586l-1.293,1.293c-0.238,0.239-0.579,0.342-0.912,0.271 c-0.115-0.024-2.842-0.611-4.502-2.271s-2.247-4.387-2.271-4.502c-0.069-0.33,0.032-0.674,0.271-0.912l1.293-1.293L6.005,5.408 L4,7.413c0.02,1.223,0.346,5.508,3.712,8.874C11.067,19.643,15.337,19.978,16.585,19.999z"
              ></path>
              <path d="M16.566 21.999c.005 0 .023 0 .028 0 .528 0 1.027-.208 1.405-.586l2.712-2.712c.391-.391.391-1.023 0-1.414l-4-4c-.391-.391-1.023-.391-1.414 0l-1.594 1.594c-.739-.22-2.118-.72-2.992-1.594s-1.374-2.253-1.594-2.992l1.594-1.594c.391-.391.391-1.023 0-1.414l-4-4c-.375-.375-1.039-.375-1.414 0L2.586 5.999C2.206 6.379 1.992 6.901 2 7.434c.023 1.424.4 6.37 4.298 10.268S15.142 21.976 16.566 21.999zM6.005 5.408l2.586 2.586L7.298 9.287c-.239.238-.341.582-.271.912.024.115.611 2.842 2.271 4.502s4.387 2.247 4.502 2.271c.333.07.674-.032.912-.271l1.293-1.293 2.586 2.586-2.006 2.005c-1.248-.021-5.518-.356-8.873-3.712C4.346 12.921 4.02 8.636 4 7.413L6.005 5.408zM19.999 10.999h2c0-5.13-3.873-8.999-9.01-8.999v2C17.051 4 19.999 6.943 19.999 10.999z"></path>
              <path d="M12.999,8c2.103,0,3,0.897,3,3h2c0-3.225-1.775-5-5-5V8z"></path>
            </svg>
          </div>
          <div
            className="TabBar___StyledDiv2-sc-cvgqr0-6 jeDtVb"
            style={{ fontSize: "12px" }}
          >
            Call
          </div>
        </a>
      </Styled.MobileFilterButtons>
      <Styled.MobileFilterButtons
        onClick={() =>
          quoteComprehesiveGrouped1 &&
          quoteComprehesiveGrouped1?.length > 0 &&
          tab !== "tab2" &&
          setMobileComp((prev) => !prev)
        }
      >
        <a className="TabBar__StyledLink-sc-cvgqr0-0 exoIhE">
          <div className="TabBar___StyledDiv-sc-cvgqr0-5 gPutWC">
            <img
              src={`${extPath}/assets/images/balance.svg`}
              height="20"
              alt="balance"
            />
          </div>
          <div
            className="TabBar___StyledDiv2-sc-cvgqr0-6 jeDtVb"
            style={{ fontSize: "12px" }}
          >
            Compare
          </div>
        </a>
      </Styled.MobileFilterButtons>
    </Styled.BottomTabsContainer>
  );
};
