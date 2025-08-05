import React from "react";
import styled from "styled-components";
import PropTypes from "prop-types";
import { Row, Table } from "react-bootstrap";
import Popup from "components/Popup/Popup";
import { useSelector } from "react-redux";

import { useMediaPredicate } from "react-media-hook";
const VehicleDetails = ({ show, onClose }) => {
  //prefill
  const { temp_data } = useSelector((state) => state.home);
  const lessthan963 = useMediaPredicate("(max-width: 963px)");
  const content = (
    <>
      <Conatiner>
        <Row>
          <VehicleDetailsHeader className="vehicleDetailsHeader">
            {" "}
            <CarWrapper>
              <CarLogo
                src={`${
                  import.meta.env.VITE_BASENAME !== "NA"
                    ? `/${import.meta.env.VITE_BASENAME}`
                    : ""
                }/assets/images/auto-car.jpg`}
                alt="car"
              />
            </CarWrapper>
            Vehicle Details{" "}
          </VehicleDetailsHeader>
          <VehicleDetailsTable className="vehicleDetailsTable">
            <Table bordered hover>
              <tbody className="detailsDataTable">
                <tr>
                  <td className="sideHead">Manufacturer</td>
                  <td className="sideData">{temp_data?.manfName}</td>
                </tr>
                <tr>
                  <td className="sideHead">Model</td>
                  <td className="sideData">{temp_data?.modelName}</td>
                </tr>
                <tr>
                  <td className="sideHead">Variant</td>
                  <td className="sideData">{temp_data?.versionName}</td>
                </tr>
                <tr>
                  <td className="sideHead">Ownership</td>
                  <td className="sideData">
                    {temp_data?.ownerTypeId === 1 ? "Individual" : "Company"}
                  </td>
                </tr>
              </tbody>
            </Table>
          </VehicleDetailsTable>
        </Row>
      </Conatiner>
    </>
  );
  return (
    <Popup
      height={"auto"}
      width="400px"
      show={show}
      onClose={onClose}
      content={content}
      position="center"
      top="15%"
      left={lessthan963 ? "50%" : "20%"}
      noBlur="true"
    />
  );
};

// PropTypes
VehicleDetails.propTypes = {
  show: PropTypes.bool,
  onClose: PropTypes.func,
};

// DefaultTypes
VehicleDetails.defaultProps = {
  show: false,
  onClose: () => {},
};

const Conatiner = styled.div`
  padding: 20px 30px;
`;

const VehicleDetailsTable = styled.div`
  width: 360px;
  min-width: 360px;
  .detailsDataTable {
    font-size: 14px;
  }
  .sideHead {
    background: #3a3a3a;
    color: #fff;
    border: ${({ theme }) => theme.QuoteCard?.border || "1px solid #bdd400"};
  }
  .sideData {
    border-bottom: ${({ theme }) =>
      theme.QuoteCard?.border || "1px solid #bdd400"};
    border-right: ${({ theme }) =>
      theme.QuoteCard?.border || "1px solid #bdd400"};
    border-top: ${({ theme }) =>
      theme.QuoteCard?.border || "1px solid #bdd400"};
    text-align: center;
  }
`;

const VehicleDetailsHeader = styled.div`
  font-size: 18px;
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100%;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  line-height: 20px;
  margin-bottom: 20px;
  margin-bottom: 10px;
`;

const CarWrapper = styled.div`
  display: flex;
  justify-content: center;
`;

const CarLogo = styled.img`
  height: 100px;
  width: 100px;
  border-radius: 20px;
  box-shadow: 0px 4px 13px rgba(41, 41, 41, 0.35);
  border: 2.5px solid #bdd400;
  position: relative;
  right: 60px;
`;

export default VehicleDetails;
