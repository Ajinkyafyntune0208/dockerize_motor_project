import React from "react";
import { Badge } from "react-bootstrap";
import { CarLogo, DetailsWrapper } from "../styles";
export const EditDetailsTop = ({
  lessthan767,
  TypeReturn,
  type,
  temp_data,
  token,
  query,
  handleEdit,
}) => {
  return (
    <DetailsWrapper>
      {!lessthan767 && (
        <CarLogo
          src={
            type === "cv" && import.meta.env.VITE_BROKER === "BAJAJ"
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
      {!temp_data?.regNo ? (
        <span className="vehicleDetails">
          {temp_data?.manfName}-{temp_data?.modelName} -{temp_data?.versionName}{" "}
        </span>
      ) : (
        <span className="vehicleDetails">
          {" "}
          {temp_data?.regNo !== "NEW"
            ? temp_data?.regNo
            : temp_data?.rtoNumber}{" "}
        </span>
      )}
      {((token && query.get("xutm") && localStorage?.SSO_user_motor) ||
        !token) && (
        <Badge
          variant="dark"
          style={{
            cursor: "pointer",
            position: "relative",
            bottom: "0px",
            left: "15px ",
            height: "24px",
            fontSize: "16px",
          }}
          onClick={handleEdit}
        >
          {"Change"}
        </Badge>
      )}
    </DetailsWrapper>
  );
};
