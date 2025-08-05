import React from "react";
import { Col } from "react-bootstrap";
import Style from "../../style";

const VehicleDetailMobile2 = ({
  lessthan600,
  isMobileIOS,
  newCar,
  userData,
}) => {
  return (
    <Col lg={6} md={6} sm={6} xs="6" className={lessthan600 ? "px-0 mx-0" : ""}>
      <Style.FilterMobileTopItem mask={true} isMobileIOS={isMobileIOS}>
        <span className="rtoNameMobile" name="rto">
          {userData?.temp_data?.regNo?.[0] * 1
            ? `${userData?.temp_data?.regNo} (${userData?.temp_data?.rtoNumber})`
            : !newCar
            ? userData?.temp_data?.regNo || userData?.temp_data?.rtoNumber
            : userData?.temp_data?.rtoNumber || userData?.temp_data?.regNo}{" "}
        </span>
      </Style.FilterMobileTopItem>
    </Col>
  );
};

export default VehicleDetailMobile2;
