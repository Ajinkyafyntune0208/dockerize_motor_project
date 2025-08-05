import React from "react";
import { Extn } from 'modules/Home/steps/lead-page/helper'
import { TypeReturn, TypeCategory } from "modules/type";

export const LeadBanner = ({theme_conf, lessthan767, type}) => {
  const { StyledH4 } = Extn
  const _renderHeader = () => {
    return (
      <>
        {" "}
        <StyledH4 className="text-center w-100 mx-auto d-flex justify-content-center">
          {lessthan767
            ? theme_conf?.broker_config?.mobile_lead_page_title
              ? theme_conf?.broker_config?.mobile_lead_page_title
                  .replace(
                    /productType2/g,
                    TypeReturn(type) === "cv"
                      ? "Vehicle"
                      : `${
                          TypeCategory(TypeReturn(type))
                            .charAt(0)
                            .toUpperCase() +
                          TypeCategory(TypeReturn(type)).slice(1)
                        }`
                  )
                  .replace(
                    /productType/gi,
                    TypeReturn(type) === "cv"
                      ? "Vehicle"
                      : `${
                          TypeReturn(type).charAt(0).toUpperCase() +
                          TypeReturn(type).slice(1)
                        }`
                  )
              : `Buy vehicle insurance in easy steps. Please fill the details`
            : theme_conf?.broker_config?.lead_page_title
            ? theme_conf?.broker_config?.lead_page_title
                .replace(
                  /productType2/g,
                  TypeReturn(type) === "cv"
                    ? "Vehicle"
                    : `${
                        TypeCategory(TypeReturn(type)).charAt(0).toUpperCase() +
                        TypeCategory(TypeReturn(type)).slice(1)
                      }`
                )
                .replace(
                  /productType/gi,
                  TypeReturn(type) === "cv"
                    ? "Vehicle"
                    : `${
                        TypeReturn(type).charAt(0).toUpperCase() +
                        TypeReturn(type).slice(1)
                      }`
                )
            : import.meta.env.VITE_BROKER === "TATA"
            ? `Get the Right Vehicle Insurance Quotes`
            : import.meta.env.VITE_BROKER === "BAJAJ"
            ? `Now Buy faster ${
                type.charAt(0).toUpperCase() + type.slice(1)
              } Insurance online`
            : `Get the best Vehicle Insurance quotes in`}
        </StyledH4>
        {!theme_conf?.broker_config?.lead_page_title &&
          !lessthan767 &&
          import.meta.env.VITE_BROKER !== "TATA" && (
            <StyledH4
              className={`text-center w-100 mx-auto d-flex justify-content-center ${
                import.meta.env.VITE_BROKER === "UIB" && "font-weight-bold"
              }  `}
            >
              {import.meta.env.VITE_BROKER === "BAJAJ"
                ? " in India."
                : " 2 minutes."}
            </StyledH4>
          )}
      </>
    );
  };

  return _renderHeader()
};
