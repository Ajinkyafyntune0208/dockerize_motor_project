import React from "react";
import { ImPencil } from "react-icons/im";
import Style from "../../style";
import { Col } from "react-bootstrap";

const RegDate = ({
  lessthan600,
  isMobileIOS,
  userData,
  allQuoteloading,
  setEditInfoPopup,
  isEditable
}) => {
  return (
    <Col lg={6} md={6} sm={6} xs="6" className={lessthan600 ? "px-0 mx-0" : ""}>
      <Style.FilterMobileTopItem
        isMobileIOS={isMobileIOS}
        onClick={
          isEditable &&
          !allQuoteloading
            ? () => {
                setEditInfoPopup(true);
              }
            : () => {}
        }
      >
        <span className="rtoNameMobile">INVOICE DATE: </span>
        <span className="rtoNameMobile">
          <text className="rtoNameMobile1" name="reg_date">
            {userData?.temp_data?.vehicleInvoiceDate
              ? `${userData?.temp_data?.vehicleInvoiceDate.split("-")[0]}/${
                  userData?.temp_data?.vehicleInvoiceDate.split("-")[1]
                }/${userData?.temp_data?.vehicleInvoiceDate.split("-")[2].slice(-2)}`
              : ""}
          </text>
          {(isEditable || userData?.temp_data?.newCar) &&
          !allQuoteloading ? (
            <ImPencil
              className="editImageMobile"
              onClick={() => setEditInfoPopup(true)}
            />
          ) : (
            <noscript />
          )}
        </span>
      </Style.FilterMobileTopItem>
    </Col>
  );
};

export default RegDate;
