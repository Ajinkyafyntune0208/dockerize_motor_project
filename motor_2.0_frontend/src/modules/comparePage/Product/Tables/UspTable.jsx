import React from "react";
import { Table } from "react-bootstrap";
import _ from "lodash";

export const UspTable = ({ quote }) => {
  return (
    <Table className="tableOne">
      {quote?.usp?.length > 0 && quote?.usp?.length < 3
        ? _.compact([
            ...quote?.usp,
            ...[...Array(3 - quote?.usp?.length)].map((v, i) => "N/A"),
          ]).map((item, index) => (
            <tr>
              <td
                className="firstTableValue"
                name="usp_desc1"
              >
                {item?.usp_desc ? item?.usp_desc : "-"}
              </td>
            </tr>
          ))
        : quote?.usp?.map((item, index) => (
            <tr>
              <td
                className="firstTableValue"
                name="usp_desc2"
              >
                {item?.usp_desc}
              </td>
            </tr>
          ))}

      {quote?.usp?.length < 1 &&
        [...Array(3)].map((elementInArray, index) => (
          <tr>
            <td
              className="firstTableValue EmptyHide"
              name="usp_desc3"
            >
              {" "}
              Dummy-text{" "}
            </td>
          </tr>
        ))}
    </Table>
  );
};

export const UspTable1 = ({ quote }) => {
  return (
    <Table className="tableOne">
      {quote?.usp?.length > 0 && quote?.usp?.length < 3
        ? _.compact([
            ...quote?.usp,
            ...[...Array(3 - quote?.usp?.length)].map((v, i) => "N/A"),
          ]).map((item, index) => (
            <tr>
              <td
                className="firstTableValue EmptyHide"
                name="usp_desc1"
              >
                Dummy-text{" "}
              </td>
            </tr>
          ))
        : quote?.usp?.map((item, index) => (
            <tr>
              <td
                className="firstTableValue EmptyHide"
                name="usp_desc2"
              >
                {" "}
                Dummy-text{" "}
              </td>
            </tr>
          ))}

      {quote?.usp?.length < 1 &&
        [...Array(3)].map((elementInArray, index) => (
          <tr>
            <td
              className="firstTableValue EmptyHide"
              name="usp_desc3"
            >
              {" "}
              Dummy-text{" "}
            </td>
          </tr>
        ))}
    </Table>
  );
};
