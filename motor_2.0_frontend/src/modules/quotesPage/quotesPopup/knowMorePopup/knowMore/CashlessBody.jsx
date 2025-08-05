import { Error, Loader } from "components";
import React from "react";
import { Col, Row, Table, Button } from "react-bootstrap";
import { MdOutlineMessage } from "react-icons/md";
import Style from "../style";

const CashlessBody = ({
  lessthan993,
  register,
  temp_data,
  prefill,
  errors,
  garage,
  clearAllGarage,
  loader,
  setSendQuotes,
  setSelectedGarage,
  setOpenGarageModal,
  companyAlias,
}) => {
  return (
    <Style.Body>
      <Row style={lessthan993 ? {} : { width: "100%" }}>
        <Col md={12} sm={12}>
          <div className="w-100 cashless_ui">
            <Style.RowTag className="row mt-4">
              <div className="col-4 col-lg-3 m-0 p-0"></div>
              <div className="col-12 col-lg-12 p-0 d-flex cashless_input">
                <input
                  type="text"
                  name="pincode"
                  ref={register}
                  className="form-control search_input"
                  placeholder="Search Garages by Pincode/City"
                  style={{
                    padding: "29px",
                    borderRadius: "8px",
                    margin: "0 25px",
                  }}
                  defaultValue={
                    temp_data?.rtoCity ||
                    prefill?.corporateVehiclesQuoteRequest?.rtoCity
                  }
                />
                <i
                  className="fa fa-search search-icon"
                  style={{
                    position: "absolute",
                    left: "100%",
                    display: "flex",
                    justifyContent: "end",
                    marginTop: "18px",
                    fontSize: "22px",
                    marginLeft: "-65px", 
                  }}
                ></i>
              </div>
            </Style.RowTag>
            {!!errors.lastName && (
              <Error style={{ marginTop: "-20px" }}>
                {errors.lastName.message}
              </Error>
            )}
          </div>
        </Col>
      </Row>

      <Table striped hover>
        <div
          className="garage_div mt-3"
          style={{
            margin: lessthan993 ? "0px" : "0px 1.5rem 0px 1.5rem",
            boxShadow: lessthan993
              ? "none"
              : "rgb(0 0 0 / 15%) 0px 5px 15px 0px",
            borderRadius: "8px",
          }}
        >
          {garage?.length > 0 && (
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                padding: "16px",
                fontSize: !lessthan993 ? "15.5px" : "12.5px",
                fontWeight: "bold",
                border: "1px solid #495057",
              }}
            >
              <p style={{ margin: "0 0 0 15px", alignSelf: "center" }}>
                Cashless Garages near you
                <Style.GarageLength>
                  {garage?.length} garages found
                </Style.GarageLength>
              </p>
              <Button variant="outline-danger"
                onClick={clearAllGarage}
                className={!lessthan993 ? "mr-3" : "m-0"}
                style={{
                  borderRadius: "5px",
                  fontWeight: "bold",
                }}
              >
                Clear All
              </Button>
            </div>
          )}
          {garage.map((item, index) => (
            <div
              key={index}
              style={{
                display: "flex",
                flexDirection: lessthan993 ? "column" : "row",
                justifyContent: "space-between",
                padding: "16px",
                border: "none",
                fontSize: !lessthan993 ? "13px" : "11px",
                margin: lessthan993 ? "none" : "15px",
                borderRadius: "8px",
                marginBottom: lessthan993 ? "20px" : "",
                borderBottom: "0.5px solid #d5d5d5",
              }}
            >
              <p style={{ paddingRight: "20px" }}>
                <strong
                  style={{
                    fontSize: !lessthan993 ? "15px" : "12px",
                  }}
                >
                  {" "}
                  {item?.garageName}{" "}
                </strong>{" "}
                <br /> {item?.garageAddress} <br />
                {item?.mobileNo && `Mobile No: ${item?.mobileNo}`}
              </p>
              <div
                onClick={() => {
                  setSelectedGarage({ ...item, companyAlias });
                  setOpenGarageModal(true);
                }}
                className="badge bg-success"
                style={{
                  fontSize: "24px",
                  fontWeight: "bold",
                  display: "flex",
                  margin: "0px",
                  color: "#fff",
                  padding: "8px",
                  height: "100%",
                  justifyContent: "center",
                  width: lessthan993 ? "max-content" : "auto",
                  cursor: "pointer",
                }}
              >
                <MdOutlineMessage />
              </div>
            </div>
          ))}
        </div>
        {loader && <Loader />}
        {garage?.length === 0 && !loader && (
          <div style={{ textAlign: "center", marginTop: "30px" }}>
            <img
              src={`${
                import.meta.env.VITE_BASENAME !== "NA"
                  ? `/${import.meta.env.VITE_BASENAME}`
                  : ""
              }/assets/images/nodata3.png`}
              width="100"
              alt="not found"
            />
            <p style={{ fontSize: "13px", fontWeight: "bold" }}>
              No Data Found
            </p>
          </div>
        )}
      </Table>
    </Style.Body>
  );
};

export default CashlessBody;
