import React from "react";
import { Col } from "react-bootstrap";
import { FiEdit } from "react-icons/fi";
import Style from "../style";
import { CustomTooltip } from "components";

const VehicleDetail = ({
  showAbiblPopup,
  location,
  type,
  reviewData,
  userData,
  newCar,
  setToasterShown,
  setShowAbiblPopup,
  setEditInfoPopup2,
  lessthan767,
}) => {
  const manfName = userData?.temp_data?.manfName;
  const modelName = userData?.temp_data?.modelName;
  const versionName = userData?.temp_data?.versionName;

  return (
    <Col lg={3} md={12}>
      <Style.FilterMenuOpenWrap highlighted={showAbiblPopup}>
        <Style.FilterMenuOpenTitle>
          <CustomTooltip
            rider={true}
            id={versionName?.length > 13 && "mmvTooltip"}
            place={"right"}
            customClassName="mt-3"
            mmvText
          >
            <label
              style={{ marginBottom: "-4px" }}
              data-tip={
                !lessthan767 &&
                `<div>Manfacture: ${manfName} <br />
                           Model: ${modelName} <br />
                         Version: ${versionName}</div>`
              }
              data-html={true}
              data-for={!lessthan767 && "mmvTooltip"}
            >
              {
                <span className="mmvTexts" name="mmv">
                  {" "}
                  {manfName}-{modelName}-
                  {versionName?.length > 13
                    ? versionName?.slice(0, 10) + "..."
                    : versionName}{" "}
                </span>
              }
            </label>
          </CustomTooltip>
        </Style.FilterMenuOpenTitle>

        <Style.FilterMenuOpenSub>
          {
            <>
              {" "}
              <span
                onClick={
                  userData?.temp_data?.corporateVehiclesQuoteRequest
                    ?.isRenewal !== "Y" ||
                  (userData?.temp_data?.corporateVehiclesQuoteRequest
                    ?.frontendTags &&
                    import.meta.env.VITE_BROKER === "BAJAJ")
                    ? () => {
                        setEditInfoPopup2(true);
                        setShowAbiblPopup(false);
                        setToasterShown(false);
                      }
                    : () => {}
                }
              >
                <span className="subTypeName">
                  {" "}
                  {userData?.temp_data?.productSubTypeCode &&
                  userData?.temp_data?.productSubTypeCode.length > 15
                    ? userData?.temp_data?.productSubTypeCode.slice(0, 15) +
                      "..."
                    : userData?.temp_data?.productSubTypeCode}
                  {" | "}
                </span>
                <span
                  style={{
                    marginLeft: "2px",
                    marginRight: "2px",
                    marginTop: "2px",
                  }}
                >
                  {userData?.temp_data?.fuel}
                </span>{" "}
                |{" "}
                {userData?.temp_data?.regNo?.[0] * 1
                  ? `${userData?.temp_data?.regNo} (${userData?.temp_data?.rtoNumber})`
                  : !newCar
                  ? userData?.temp_data?.regNo || userData?.temp_data?.rtoNumber
                  : userData?.temp_data?.rtoNumber ||
                    userData?.temp_data?.regNo}{" "}
                <FiEdit
                  className="blueIcon"
                  id="rtoId"
                  style={
                    userData?.temp_data?.corporateVehiclesQuoteRequest
                      ?.isRenewal === "Y"
                      ? { visibility: "hidden" }
                      : {}
                  }
                />
              </span>
            </>
          }{" "}
        </Style.FilterMenuOpenSub>
      </Style.FilterMenuOpenWrap>
    </Col>
  );
};

export default VehicleDetail;
