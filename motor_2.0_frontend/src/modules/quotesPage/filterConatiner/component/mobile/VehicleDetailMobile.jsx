import React from "react";
import { Col } from "react-bootstrap";
import Style from "../../style";
import { ImPencil } from "react-icons/im";

const VehicleDetailMobile = ({
  lessthan600,
  isMobileIOS,
  userData,
  setEditInfoPopup2,
}) => {
  return (
    <Col lg={6} md={6} sm={6} xs="6" className={lessthan600 ? "px-0 mx-0" : ""}>
      <Style.FilterMobileTopItem
        mask={true}
        isMobileIOS={isMobileIOS}
        onClick={
          (userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal !==
            "Y" || (userData?.temp_data?.corporateVehiclesQuoteRequest?.frontendTags && import.meta.env.VITE_BROKER === "BAJAJ"))
            ? () => setEditInfoPopup2(true)
            : () => {}
        }
        style={{ display: "flex", justifyContent: "flex-start" }}
      >
        <span className="rtoNameMobile" style={{ paddingRight: "5px" }} name="mmv">
          <>
            {userData?.temp_data?.manfName}-{userData?.temp_data?.modelName}-
            {userData?.temp_data?.versionName}{" "}
          </>
        </span>
        {(userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal !==
                  "Y" || (userData?.temp_data?.corporateVehiclesQuoteRequest?.frontendTags && import.meta.env.VITE_BROKER === "BAJAJ")) && (
          <ImPencil
            className="editImageMobile"
            onClick={() => setEditInfoPopup2(true)}
          />
        )}
      </Style.FilterMobileTopItem>
    </Col>
  );
};

export default VehicleDetailMobile;
