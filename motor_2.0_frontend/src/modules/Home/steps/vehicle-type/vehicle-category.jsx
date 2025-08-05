import React from "react";
import { Row } from "react-bootstrap";
import { _haptics } from "utils";
import styled from "styled-components";
import _ from "lodash";

import { MdOutlineElectricRickshaw } from "react-icons/md"; //rickshaw
import { FaTaxi } from "react-icons/fa"; //taxi
import { FaTruck } from "react-icons/fa"; //van
import { GiMineTruck } from "react-icons/gi"; //dumper-tripper
import { PiTruckFill } from "react-icons/pi"; //truck
import { FaTractor } from "react-icons/fa"; //tractor
import { GiTowTruck } from "react-icons/gi"; //misc-d
import { PiTruck } from "react-icons/pi"; //tanker-bulker
import { GiTruck } from "react-icons/gi"; //tempo-traveller
import { GiFarmTractor } from "react-icons/gi"; //Agricultural Tractor




const VehicleCategory = ({
  vehicleType,
  lessthan963,
  lessthan600,
  lessthan400,
  btnDisable,
  setbtnDisable,
  onSubmit,
  selected,
  Theme,
}) => {
  const getIcon = (name) => {
    switch (name) {
      case "AUTO-RICKSHAW":
        return <MdOutlineElectricRickshaw className="svgImage" />;
      case "TAXI":
        return <FaTaxi className="svgImage" />;
      case "ELECTRIC-RICKSHAW":
        return <MdOutlineElectricRickshaw className="svgImage" />;
      case "PICK UP/DELIVERY/REFRIGERATED VAN":
        return <FaTruck className="svgImage" />;
      case "DUMPER/TIPPER":
        return <GiMineTruck className="svgImage" />;
      case "TRUCK":
        return <PiTruckFill className="svgImage" />;
      case "TRACTOR":
        return <FaTractor className="svgImage" />;
      case "MISCELLANEOUS-CLASS":
        return <GiTowTruck className="svgImage" />;
      case "TANKER/BULKER":
        return <PiTruck className="svgImage" />;
      case "TEMPO-TRAVELLER":
        return <GiTruck className="svgImage" />;
      case "AGRICULTURAL-TRACTOR":
        return <GiFarmTractor className="svgImage" />;
      default:
        return "";
    }
  };

  return (
    <Row className="d-flex justify-content-center w-100 mt-4">
      {vehicleType?.filter(
        ({ productCategoryName }) => productCategoryName === "PCV"
      ).length > 0 && (
        <MainCard theme={Theme}>
          <Heading
            theme={Theme}
            lessthan963={lessthan963}
            lessthan400={lessthan400}
          >
            Passenger Carrying Vehicle
          </Heading>
          <CardWrapper lessthan963={lessthan963} lessthan600={lessthan600}>
            {vehicleType
              ?.filter(
                ({ productCategoryName }) => productCategoryName === "PCV"
              )
              .map(
                (
                  {
                    productSubTypeId,
                    productSubTypeCode,
                    productSubTypelogo,
                    productCategoryName,
                    productSubTypeDesc,
                  },
                  index
                ) => (
                  <Card
                    key={index}
                    theme={Theme}
                    lessthan963={lessthan963}
                    lessthan400={lessthan400}
                    onClick={() => {
                      _haptics([100, 0, 50]);
                      onSubmit(productSubTypeId);
                      setbtnDisable(true);
                    }}
                  >
                    {getIcon(productSubTypeCode) || (
                      <img src={productSubTypelogo} alt="logo" />
                    )}
                    {/* <img src={data[productSubTypeId].default} alt="logo" /> */}
                    <span key={index}>{productSubTypeCode}</span>
                  </Card>
                )
              )}
          </CardWrapper>
        </MainCard>
      )}

      {vehicleType?.filter(
        ({ productCategoryName }) => productCategoryName === "GCV"
      ).length > 0 && (
        <MainCard theme={Theme}>
          <Heading
            theme={Theme}
            lessthan963={lessthan963}
            lessthan400={lessthan400}
          >
            Goods Carrying Vehicle
          </Heading>
          <CardWrapper lessthan963={lessthan963} lessthan600={lessthan600}>
            {vehicleType
              ?.filter(
                ({ productCategoryName }) => productCategoryName === "GCV"
              )
              .map(
                (
                  {
                    productSubTypeId,
                    productSubTypeCode,
                    productSubTypelogo,
                    productCategoryName,
                    productSubTypeDesc,
                  },
                  index
                ) => (
                  <Card
                    key={index}
                    theme={Theme}
                    lessthan963={lessthan963}
                    lessthan400={lessthan400}
                    onClick={() => {
                      _haptics([100, 0, 50]);
                      onSubmit(productSubTypeId, "PUBLIC");
                      setbtnDisable(true);
                    }}
                  >
                    {getIcon(productSubTypeCode) || (
                      <img src={productSubTypelogo} alt="logo" />
                    )}
                    {/* <img src={data[productSubTypeId].default} alt="logo" /> */}
                    <span key={index}>
                      {productSubTypeId === 9
                        ? "REFRIGERATED VAN"
                        : productSubTypeCode}
                    </span>
                  </Card>
                )
              )}
          </CardWrapper>
        </MainCard>
      )}

      {vehicleType?.filter(
        ({ productCategoryName }) => productCategoryName === "MISC"
      ).length > 0 && (
        <MainCard theme={Theme}>
          <Heading
            theme={Theme}
            lessthan963={lessthan963}
            lessthan400={lessthan400}
          >
            MISCELLANEOUS-CLASS
          </Heading>
          <CardWrapper lessthan963={lessthan963} lessthan600={lessthan600}>
            {vehicleType
              ?.filter(
                ({ productCategoryName }) => productCategoryName === "MISC"
              )
              .map(
                (
                  {
                    productSubTypeId,
                    productSubTypeCode,
                    productSubTypelogo,
                    productCategoryName,
                    productSubTypeDesc,
                  },
                  index
                ) => (
                  <Card
                    key={index}
                    theme={Theme}
                    lessthan963={lessthan963}
                    lessthan400={lessthan400}
                    onClick={() => {
                      _haptics([100, 0, 50]);
                      onSubmit(productSubTypeId);
                      setbtnDisable(true);
                    }}
                  >
                    {getIcon(productSubTypeCode) || (
                      <img src={productSubTypelogo} alt="logo" />
                    )}{" "}
                    {/* <img src={data[productSubTypeId].default} alt="logo" /> */}
                    <span key={index}>{productSubTypeCode}</span>
                  </Card>
                )
              )}
          </CardWrapper>
        </MainCard>
      )}
    </Row>
  );
};

export default VehicleCategory;

export const Heading = styled.p`
  width: max-content;
  font-size: ${({ lessthan963, lessthan400 }) =>
    lessthan963 || lessthan400 ? (lessthan400 ? "18px" : "15px") : "20px"};
  font-weight: 600;
  color: #000000;
  border-bottom: 4px solid transparent;
  border-image: linear-gradient(
    to right,
    ${({ theme }) => theme?.prevPolicy?.color1 || "#000"},
    white
  );
  border-image-slice: 1;
  text-transform: uppercase;
`;

export const MainCard = styled.div`
  width: 100%;
  padding: 16px 10px;
`;

export const CardWrapper = styled.div`
  display: grid;
  grid-template-columns: repeat(
    ${({ lessthan600 }) => (lessthan600 ? 2 : 3)},
    1fr
  );
  justify-content: center;
  gap: 1rem;
  color: ${({ theme }) => theme?.prevPolicy?.color1 || "#000"};
  cursor: pointer;
`;

export const Card = styled.div`
  padding: 0 15px;
  height: ${({ lessthan963 }) => (lessthan963 ? "50px" : "60px")};
  display: flex;
  align-items: center;
  gap: 15px;
  border-radius: 10px;
  border: 1px solid
    ${({ theme }) => theme?.prevPolicy?.color1 || "1px solid #000"};

  &:hover {
    background-color: ${({ theme }) => theme?.prevPolicy?.color1 || "#000"};
    color: #fff;
    img {
      filter: invert(1);
    }
    transition: all 0.2s ease-in-out;
     
  }
.svgImage {
       font-size: 30px;
       margin-left: 10px;
     }
  img {
    // color: ${({ theme }) => theme?.prevPolicy?.color1 || "#000"};
    width: ${({ lessthan963, lessthan400 }) =>
      lessthan963 || lessthan400 ? (lessthan400 ? "30px" : "40px") : "50px"};
    aspect-ratio: 20 / 10;
    filter: ${({ theme }) =>
      theme?.VehicleType?.filterIconCol ||
      `invert(42%) sepia(93%) saturate(1352%) hue-rotate(87deg)
			brightness(90%) contrast(119%)}`};
  }

  span {
    font-size: ${({ lessthan963, lessthan400 }) =>
      lessthan963 || lessthan400 ? (lessthan400 ? "8px" : "10px") : "12px"};
    font-weight: ${({ lessthan963 }) => (lessthan963 ? "600" : "800")};
  }
`;
