import React from "react";
import "./compare-plan.scss";
import { createGlobalStyle } from "styled-components";
import CancelIcon from "@material-ui/icons/Cancel";
import { useMediaPredicate } from "react-media-hook";

export const ComparePlan = ({ plan, logoUrl, id }) => {
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  return (
    <>
      <div
        className="compare-box"
        style={{ border: plan && "solid 1px #e3e4e8", padding: lessthan767 && "2px" }}
      >
        {plan ? (
          <div className="compare-box__inside">
            <div
              style={{ cursor: "pointer" }}
              className="closeBtnCompare"
              onClick={() =>
                document.getElementById(`reviewAgree${id}`) && id
                  ? document.getElementById(`reviewAgree${id}`).click()
                  : {}
              }
            >
              <CancelIcon fontSize="small"/>
            </div>

            {lessthan767 ? (
              <img
                className="compare-box__logo"
                src={logoUrl}
                alt="quote-logo"
                style={lessthan767 && { width: "100%", height: "100%" , objectFit:"contain"}}
              />
            ) : (
              <img
                className="compare-box__logo"
                src={logoUrl}
                alt="quote-logo"
              />
            )}

            {!lessthan767 && (
              <div className="compare-box__plan-name">{plan}</div>
            )}
           
          </div>
        ) : (
          <div className="compare-box__inside no-plan">Add Plans</div>
        )}
      </div>
    </>
  );
};