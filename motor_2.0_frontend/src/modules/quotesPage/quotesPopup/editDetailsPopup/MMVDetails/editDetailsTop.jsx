import React from "react";
import { DetailsWrapper, CarLogo } from "../styles";
export const EditDetailsTop = ({
  lessthan767,
  TypeReturn,
  type,
  temp_data,
}) => {
  return (
    <DetailsWrapper>
      {!lessthan767 && (
        <CarLogo
          src={
            TypeReturn(type) === "cv" && import.meta.env.VITE_BROKER === "BAJAJ"
              ? `${
                  import.meta.env.VITE_BASENAME !== "NA"
                    ? `/${import.meta.env.VITE_BASENAME}`
                    : ""
                }/assets/images/vehicle/cvBlack.png`
              : TypeReturn(type) !== "bike"
              ? `${
                  import.meta.env.VITE_BASENAME !== "NA"
                    ? `/${import.meta.env.VITE_BASENAME}`
                    : ""
                }/assets/images/new-car.jpg`
              : `${
                  import.meta.env.VITE_BASENAME !== "NA"
                    ? `/${import.meta.env.VITE_BASENAME}`
                    : ""
                }/assets/images/vehicle/bike2.png`
          }
          alt="car"
        />
      )}
      <span className="vehicleDetails">
        {temp_data?.manfName}-{temp_data?.modelName} -{temp_data?.versionName}{" "}
      </span>
    </DetailsWrapper>
  );
};
